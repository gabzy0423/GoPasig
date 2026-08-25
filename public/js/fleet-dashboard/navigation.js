/**
 * Navigation and on-demand module loader for the Fleet Dashboard.
 * Overview is available immediately; other modules render and initialize on first activation.
 */

(function () {
    const screens = [
        'overview', 'monitor', 'utilization', 'drivers',
        'routes', 'incidents', 'maintenance',
        'analytics', 'dispatch-intelligence', 'commuter-trips',
        'commuter-sessions', 'profile'
    ];

    const screenLabels = {
        overview: 'Overview',
        monitor: 'Live Monitor',
        utilization: 'Fleet Utilization',
        drivers: 'Driver Performance',
        routes: 'Route Performance',
        incidents: 'Incident Reports',
        maintenance: 'Maintenance',
        analytics: 'Analytics',
        'dispatch-intelligence': 'Dispatch Intelligence',
        'commuter-trips': 'Commuter Trip Log',
        'commuter-sessions': 'Active Commuter Sessions',
        profile: 'Account Profile',
    };

    const screenIcons = {
        overview: 'ti-layout-dashboard',
        monitor: 'ti-map-pin',
        utilization: 'ti-chart-donut',
        drivers: 'ti-id',
        routes: 'ti-route',
        incidents: 'ti-alert-triangle',
        maintenance: 'ti-tool',
        analytics: 'ti-chart-bar',
        'dispatch-intelligence': 'ti-brain',
        'commuter-trips': 'ti-clipboard-list',
        'commuter-sessions': 'ti-key',
        profile: 'ti-user-circle',
    };

    const loadedScripts = new Map();
    const initializedModules = new Set();
    const loadingModules = new Map();
    const modulePollers = new Map();
    let activeScreenName = 'overview';
    let activationSequence = 0;

    function config() {
        return window.GoPasigFleetModuleLoaderConfig || {};
    }

    function moduleNeedsEcharts(screenName) {
        return ['analytics', 'drivers', 'routes', 'dispatch-intelligence'].includes(screenName);
    }

    function loadScriptOnce(src) {
        if (!src) return Promise.resolve();
        if (loadedScripts.has(src)) return loadedScripts.get(src);

        const existing = Array.from(document.scripts).find(script => script.src === new URL(src, window.location.origin).href);
        if (existing) {
            const resolved = Promise.resolve();
            loadedScripts.set(src, resolved);
            return resolved;
        }

        const promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.defer = true;
            script.dataset.fleetModuleAsset = 'true';
            script.onload = () => {
                script.dataset.loaded = 'true';
                resolve();
            };
            script.onerror = () => {
                loadedScripts.delete(src);
                script.remove();
                reject(new Error(`Failed to load script: ${src}`));
            };
            document.body.appendChild(script);
        });
        loadedScripts.set(src, promise);
        return promise;
    }

    function executeInlineScripts(container) {
        container.querySelectorAll('script').forEach(script => {
            const executable = document.createElement('script');
            Array.from(script.attributes).forEach(attr => executable.setAttribute(attr.name, attr.value));
            executable.textContent = script.textContent;
            script.remove();
            document.body.appendChild(executable);
            executable.remove();
        });
    }

    function moduleContainer(screenName) {
        return document.getElementById(`screen-${screenName}`);
    }

    function setModuleLoading(screenName) {
        const placeholder = moduleContainer(screenName);
        if (!placeholder || placeholder.dataset.loaded === 'true') return;

        placeholder.dataset.loaded = 'false';
        placeholder.dataset.loadState = 'loading';
        placeholder.setAttribute('data-fleet-module-placeholder', screenName);
        placeholder.setAttribute('aria-busy', 'true');
        placeholder.setAttribute('aria-live', 'polite');
        placeholder.innerHTML = `
            <div class="flex min-h-[320px] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 text-center">
                <div class="space-y-3">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#003F87] shadow-sm">
                        <i class="ti ti-loader-2 animate-spin text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-800">Loading ${screenLabels[screenName] || 'module'}</p>
                        <p class="text-xs font-semibold text-slate-500">Fetching the latest operational data...</p>
                    </div>
                </div>
            </div>`;
    }

    function setModuleError(screenName, message) {
        const placeholder = moduleContainer(screenName);
        if (!placeholder) return;

        placeholder.dataset.loaded = 'false';
        placeholder.dataset.loadState = 'error';
        placeholder.setAttribute('data-fleet-module-placeholder', screenName);
        placeholder.setAttribute('aria-busy', 'false');
        placeholder.innerHTML = `
            <div class="flex min-h-[320px] items-center justify-center rounded-xl border border-red-100 bg-red-50/70 text-center">
                <div class="space-y-3">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-red-600 shadow-sm">
                        <i class="ti ti-alert-circle text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-800">Unable to load ${screenLabels[screenName] || 'module'}</p>
                        <p class="fleet-module-error-message text-xs font-semibold text-slate-500"></p>
                    </div>
                    <button type="button" data-fleet-module-retry="${screenName}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-red-200 bg-white px-3 text-xs font-extrabold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200">
                        <i class="ti ti-refresh text-sm"></i>
                        Retry
                    </button>
                </div>
            </div>`;
        const messageElement = placeholder.querySelector('.fleet-module-error-message');
        if (messageElement) messageElement.textContent = message;
    }

    async function fetchModuleFragment(screenName) {
        if (screenName === 'overview') return;

        const existing = document.getElementById(`screen-${screenName}`);
        if (existing && existing.dataset.loaded === 'true') return;
        if (loadingModules.has(screenName)) return loadingModules.get(screenName);

        const loadPromise = (async () => {
            setModuleLoading(screenName);
            const url = new URL(config().fragmentUrl || window.location.pathname, window.location.origin);
            url.searchParams.set('tab', screenName);
            url.searchParams.set('fragment', '1');

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (!payload.success || !payload.html) throw new Error('Empty module response');

            const template = document.createElement('template');
            template.innerHTML = payload.html.trim();

            const incoming = template.content.firstElementChild;
            const placeholder = moduleContainer(screenName);
            if (!incoming || !placeholder) throw new Error('Module container missing');

            incoming.dataset.loaded = 'loading';
            incoming.dataset.loadState = 'loading';
            incoming.setAttribute('data-fleet-module', screenName);
            incoming.setAttribute('data-fleet-module-placeholder', screenName);
            incoming.setAttribute('aria-busy', 'true');
            placeholder.replaceWith(incoming);

            if (activeScreenName === screenName) {
                incoming.classList.remove('hidden');
                incoming.style.display = '';
            }

            executeInlineScripts(incoming);

            await loadModuleAssets(screenName);
            initializeModuleOnce(screenName);

            incoming.dataset.loaded = 'true';
            incoming.dataset.loadState = 'ready';
            incoming.removeAttribute('data-fleet-module-placeholder');
            incoming.setAttribute('aria-busy', 'false');

            return true;
        })().catch(error => {
            console.error(`Failed to load Fleet module ${screenName}:`, error);
            setModuleError(screenName, error.message || 'Please try again.');
            throw error;
        }).finally(() => {
            loadingModules.delete(screenName);
        });

        loadingModules.set(screenName, loadPromise);
        return loadPromise;
    }

    async function loadModuleAssets(screenName) {
        if (moduleNeedsEcharts(screenName) && !window.echarts) {
            await loadScriptOnce(config().assets?.echarts);
        }

        const scriptUrl = config().scripts?.[screenName];
        if (scriptUrl) {
            await loadScriptOnce(scriptUrl);
        }
    }

    function initializeModuleOnce(screenName) {
        if (initializedModules.has(screenName)) return;

        const initializers = {
            analytics: () => window.initFleetAnalyticsModule?.(),
            drivers: () => window.initFleetPerformanceModule?.('drivers'),
            routes: () => window.initFleetPerformanceModule?.('routes'),
            incidents: () => window.initFleetIncidentsModule?.(),
            maintenance: () => window.initFleetMaintenanceModule?.(),
            'dispatch-intelligence': () => window.initFleetDispatchModule?.(),
            'commuter-trips': () => window.initFleetCommuterTripsModule?.(),
            'commuter-sessions': () => window.initFleetCommuterSessionsModule?.(),
            profile: () => window.initStaffProfileModule?.() || window.loadDispatcherProfileData?.(),
        };

        initializers[screenName]?.();
        initializedModules.add(screenName);
    }

    function runModulePoller(poller) {
        if (poller.inFlight || document.hidden || activeScreenName !== poller.screenName) return;

        poller.inFlight = true;
        Promise.resolve()
            .then(() => poller.callback())
            .catch(error => console.error(`Fleet poller ${poller.key} failed:`, error))
            .finally(() => {
                poller.inFlight = false;
            });
    }

    function startModulePoller(poller, refreshNow = false) {
        if (poller.intervalId || document.hidden || activeScreenName !== poller.screenName) return;

        if (refreshNow && poller.hasStarted) runModulePoller(poller);
        poller.intervalId = window.setInterval(() => runModulePoller(poller), poller.delay);
        poller.hasStarted = true;
    }

    function stopModulePoller(poller) {
        if (!poller.intervalId) return;
        window.clearInterval(poller.intervalId);
        poller.intervalId = null;
    }

    function syncModulePollers(refreshActive = false) {
        modulePollers.forEach(poller => {
            if (!document.hidden && poller.screenName === activeScreenName) {
                startModulePoller(poller, refreshActive);
            } else {
                stopModulePoller(poller);
            }
        });
    }

    function registerModulePoller(screenName, pollerName, callback, delay) {
        if (!screens.includes(screenName) || typeof callback !== 'function') return () => {};

        const key = `${screenName}:${pollerName}`;
        const existing = modulePollers.get(key);
        if (existing) stopModulePoller(existing);

        const poller = {
            key,
            screenName,
            callback,
            delay: Math.max(Number(delay) || 10000, 1000),
            intervalId: null,
            inFlight: false,
            hasStarted: false,
        };

        modulePollers.set(key, poller);
        startModulePoller(poller, false);

        return () => {
            const current = modulePollers.get(key);
            if (current !== poller) return;
            stopModulePoller(poller);
            modulePollers.delete(key);
        };
    }

    function updateNavigationState(screenName) {
        document.querySelectorAll('[data-nav]').forEach(btn => {
            btn.classList.remove('bg-white/12', 'text-white');
            btn.classList.add('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
        });

        const activeNavBtn = document.querySelector(`[data-nav="${screenName}"]`);
        if (activeNavBtn) {
            activeNavBtn.classList.remove('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
            activeNavBtn.classList.add('bg-white/12', 'text-white');
        }

        const commuterMenu = document.getElementById('commuter-dropdown-menu');
        const commuterArrow = document.getElementById('commuter-dropdown-arrow');
        if (commuterMenu && commuterArrow) {
            if (screenName === 'commuter-trips' || screenName === 'commuter-sessions') {
                commuterMenu.classList.remove('hidden');
                commuterArrow.classList.add('rotate-180');
            } else {
                commuterMenu.classList.add('hidden');
                commuterArrow.classList.remove('rotate-180');
            }
        }

        const breadcrumbCurrent = document.getElementById('breadcrumb-current');
        const breadcrumbIcon = document.getElementById('breadcrumb-icon');
        if (breadcrumbCurrent) breadcrumbCurrent.textContent = screenLabels[screenName] || 'Overview';
        if (breadcrumbIcon) breadcrumbIcon.className = `ti ${screenIcons[screenName] || 'ti-bus'} text-sm text-slate-900`;
    }

    function switchScreen(screenName) {
        const previousScreen = activeScreenName;

        screens.forEach(screen => {
            const el = document.getElementById(`screen-${screen}`);
            if (el) {
                el.classList.add('hidden');
                el.style.display = 'none';
            }
        });

        activeScreenName = screenName;
        updateNavigationState(screenName);

        const targetScreen = document.getElementById(`screen-${screenName}`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');
            targetScreen.style.display = screenName === 'overview' ? 'block' : '';
            setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
            if (previousScreen !== screenName) {
                window.dispatchEvent(new CustomEvent('screen-hidden', { detail: { screen: previousScreen } }));
            }
            window.dispatchEvent(new CustomEvent('screen-shown', { detail: { screen: screenName } }));
        }

        syncModulePollers(previousScreen !== screenName);
    }

    async function activateFleetModule(screenName, pushState = true) {
        if (!screens.includes(screenName)) screenName = 'overview';
        const activationId = ++activationSequence;

        switchScreen(screenName);

        try {
            await fetchModuleFragment(screenName);
            if (activationId !== activationSequence) return;

            const targetScreen = moduleContainer(screenName);
            if (targetScreen) {
                targetScreen.classList.remove('hidden');
                targetScreen.style.display = screenName === 'overview' ? 'block' : '';
            }

            if (pushState) {
                const url = new URL(window.location.href);
                if (screenName === 'overview') url.searchParams.delete('tab');
                else url.searchParams.set('tab', screenName);
                url.searchParams.delete('fragment');
                window.history.pushState({ fleetTab: screenName }, '', url.toString());
            }
        } catch (error) {
            if (activationId !== activationSequence) return;
            const targetScreen = moduleContainer(screenName);
            if (targetScreen) {
                targetScreen.classList.remove('hidden');
                targetScreen.style.display = '';
            }
        }
    }

    window.switchScreen = switchScreen;
    window.activateFleetModule = activateFleetModule;
    window.GoPasigFleetModules = {
        activate: activateFleetModule,
        initialize: initializeModuleOnce,
        registerPoller: registerModulePoller,
        activeScreen: () => activeScreenName,
        loadedScripts,
        initializedModules,
        modulePollers,
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-nav]').forEach(link => {
            link.addEventListener('click', event => {
                const screenName = link.getAttribute('data-nav') || 'overview';
                event.preventDefault();
                activateFleetModule(screenName);
            });
        });

        document.addEventListener('click', event => {
            const retryButton = event.target.closest('[data-fleet-module-retry]');
            if (!retryButton) return;

            const screenName = retryButton.getAttribute('data-fleet-module-retry');
            if (!screens.includes(screenName)) return;

            event.preventDefault();
            const currentTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
            activateFleetModule(screenName, currentTab !== screenName);
        });

        window.addEventListener('popstate', () => {
            const activeTab = new URLSearchParams(window.location.search).get('tab') || 'overview';
            activateFleetModule(activeTab, false);
        });

        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = config().initialTab || urlParams.get('tab') || 'overview';
        const focusBus = urlParams.get('bus');
        const focusRoute = urlParams.get('route');

        if ((focusBus && focusBus !== 'undefined' && focusBus !== 'null' && focusBus.trim() !== '') ||
            (focusRoute && focusRoute !== 'undefined' && focusRoute !== 'null' && focusRoute.trim() !== '')) {
            activateFleetModule('monitor', false);
        } else {
            activateFleetModule(activeTab, false);
        }
    });

    document.addEventListener('visibilitychange', () => {
        syncModulePollers(!document.hidden);
    });

    window.addEventListener('pagehide', () => {
        modulePollers.forEach(stopModulePoller);
    });
}());
