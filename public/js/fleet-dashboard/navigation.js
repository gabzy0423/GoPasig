/**
 * Navigation and on-demand module loader for the Fleet Dashboard.
 * Overview is available immediately; other modules render and initialize on first activation.
 */

(function () {
    const screens = [
        'overview', 'monitor', 'utilization', 'drivers',
        'routes', 'schedule', 'incidents', 'maintenance',
        'analytics', 'dispatch-intelligence', 'commuter-trips',
        'commuter-sessions', 'profile'
    ];

    const screenLabels = {
        overview: 'Overview',
        monitor: 'Live Monitor',
        utilization: 'Fleet Utilization',
        drivers: 'Driver Performance',
        routes: 'Route Performance',
        schedule: 'Schedule Compliance',
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
        schedule: 'ti-calendar-time',
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

    function config() {
        return window.GoPasigFleetModuleLoaderConfig || {};
    }

    function moduleNeedsEcharts(screenName) {
        return ['analytics', 'drivers', 'routes', 'schedule', 'dispatch-intelligence'].includes(screenName);
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
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));
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

    function setModuleLoading(screenName, isLoading) {
        const placeholder = document.querySelector(`[data-fleet-module-placeholder="${screenName}"]`);
        if (!placeholder) return;
        const icon = placeholder.querySelector('.ti-loader-2');
        if (icon) icon.classList.toggle('animate-spin', isLoading);
        placeholder.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function setModuleError(screenName, message) {
        const placeholder = document.querySelector(`[data-fleet-module-placeholder="${screenName}"]`);
        if (!placeholder) return;
        placeholder.innerHTML = `
            <div class="flex min-h-[320px] items-center justify-center rounded-xl border border-red-100 bg-red-50/70 text-center">
                <div class="space-y-3">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-red-600 shadow-sm">
                        <i class="ti ti-alert-circle text-lg"></i>
                    </div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-800">Unable to load ${screenLabels[screenName] || 'module'}</p>
                        <p class="text-xs font-semibold text-slate-500">${message}</p>
                    </div>
                </div>
            </div>`;
    }

    async function fetchModuleFragment(screenName) {
        if (screenName === 'overview') return;

        const existing = document.getElementById(`screen-${screenName}`);
        if (existing && existing.dataset.loaded === 'true') return;
        if (loadingModules.has(screenName)) return loadingModules.get(screenName);

        const loadPromise = (async () => {
            setModuleLoading(screenName, true);
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
            const placeholder = document.getElementById(`screen-${screenName}`);
            if (!incoming || !placeholder) throw new Error('Module container missing');

            incoming.dataset.loaded = 'true';
            incoming.setAttribute('data-fleet-module', screenName);
            placeholder.replaceWith(incoming);
            executeInlineScripts(incoming);

            await loadModuleAssets(screenName);
            initializeModuleOnce(screenName);
        })().catch(error => {
            console.error(`Failed to load Fleet module ${screenName}:`, error);
            setModuleError(screenName, error.message || 'Please try again.');
            throw error;
        }).finally(() => {
            setModuleLoading(screenName, false);
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
        initializedModules.add(screenName);

        const initializers = {
            analytics: () => window.initFleetAnalyticsModule?.(),
            drivers: () => window.initFleetPerformanceModule?.('drivers'),
            routes: () => window.initFleetPerformanceModule?.('routes'),
            schedule: () => window.initFleetScheduleModule?.(),
            incidents: () => window.initFleetIncidentsModule?.(),
            maintenance: () => window.initFleetMaintenanceModule?.(),
            'dispatch-intelligence': () => window.initFleetDispatchModule?.(),
            'commuter-trips': () => window.initFleetCommuterTripsModule?.(),
            'commuter-sessions': () => window.initFleetCommuterSessionsModule?.(),
            profile: () => window.initStaffProfileModule?.() || window.loadDispatcherProfileData?.(),
        };

        initializers[screenName]?.();
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
        screens.forEach(screen => {
            const el = document.getElementById(`screen-${screen}`);
            if (el) {
                el.classList.add('hidden');
                el.style.display = 'none';
            }
        });

        updateNavigationState(screenName);

        const targetScreen = document.getElementById(`screen-${screenName}`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');
            targetScreen.style.display = screenName === 'overview' ? 'block' : '';
            setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
            window.dispatchEvent(new CustomEvent('screen-shown', { detail: { screen: screenName } }));
        }
    }

    async function activateFleetModule(screenName, pushState = true) {
        if (!screens.includes(screenName)) screenName = 'overview';

        try {
            await fetchModuleFragment(screenName);
            switchScreen(screenName);
            if (pushState) {
                const url = new URL(window.location.href);
                if (screenName === 'overview') url.searchParams.delete('tab');
                else url.searchParams.set('tab', screenName);
                url.searchParams.delete('fragment');
                window.history.pushState({ fleetTab: screenName }, '', url.toString());
            }
        } catch (error) {
            switchScreen(screenName);
        }
    }

    window.switchScreen = switchScreen;
    window.activateFleetModule = activateFleetModule;
    window.GoPasigFleetModules = {
        activate: activateFleetModule,
        initialize: initializeModuleOnce,
        loadedScripts,
        initializedModules,
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-nav]').forEach(link => {
            link.addEventListener('click', event => {
                const screenName = link.getAttribute('data-nav') || 'overview';
                event.preventDefault();
                activateFleetModule(screenName);
            });
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
}());