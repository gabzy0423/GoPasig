    // Sidebar Dropdown Accordion Helpers
    function toggleSidebarDropdown(dropdownId) {
        const menu = document.getElementById(`menu-${dropdownId}`);
        const chevron = document.getElementById(`chevron-${dropdownId}`);
        if (menu && chevron) {
            const isHidden = menu.classList.contains('hidden');
            if (isHidden) {
                menu.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                menu.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }
    }

    function updateSidebarDropdowns(activeHighlightName) {
        const dropdowns = ['routes', 'analytics'];
        let activeDropdown = null;
        if (activeHighlightName === 'routes-stops' || activeHighlightName === 'routes-schedule') {
            activeDropdown = 'routes';
        } else if (activeHighlightName.startsWith('analytics-')) {
            activeDropdown = 'analytics';
        }

        dropdowns.forEach(id => {
            const menu = document.getElementById(`menu-${id}`);
            const chevron = document.getElementById(`chevron-${id}`);
            if (menu && chevron) {
                if (id === activeDropdown) {
                    menu.classList.remove('hidden');
                    chevron.classList.add('rotate-180');
                } else {
                    menu.classList.add('hidden');
                    chevron.classList.remove('rotate-180');
                }
            }
        });
    }

    function navigateToRoutesTab(tab) {
        if (typeof switchRoutesTab === 'function') {
            switchRoutesTab(tab);
        } else {
            window.activeRoutesTab = tab;
        }
        window.location.hash = tab === 'stops' ? 'routes-stops' : 'routes-schedule';
    }

    function navigateToAnalyticsSection(sectionId) {
        window.location.hash = sectionId;
    }

    // Expose helpers globally
    window.toggleSidebarDropdown = toggleSidebarDropdown;
    window.updateSidebarDropdowns = updateSidebarDropdowns;
    window.navigateToRoutesTab = navigateToRoutesTab;
    window.navigateToAnalyticsSection = navigateToAnalyticsSection;

    // Toggle Responsive Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Switch Screens seamlessly
    function switchScreen(screenName) {
        // Resolve parent screen name and sub-nav highlight key
        let parentScreenName = screenName;
        let navHighlightName = screenName;

        if (screenName === 'analytics') {
            parentScreenName = 'analytics-fleet-utilization';
            navHighlightName = 'analytics-fleet-utilization';
        } else if (screenName === 'routes-stops') {
            parentScreenName = 'routes';
            navHighlightName = 'routes-stops';
        } else if (screenName === 'routes-schedule') {
            parentScreenName = 'routes';
            navHighlightName = 'routes-schedule';
        } else if (screenName === 'routes') {
            parentScreenName = 'routes';
            const activeTab = (typeof activeRoutesTab !== 'undefined') ? activeRoutesTab : 'schedule';
            navHighlightName = activeTab === 'stops' ? 'routes-stops' : 'routes-schedule';
        }

        // Hide all screens
        document.getElementById('screen-overview').classList.add('hidden');
        document.getElementById('screen-buses').classList.add('hidden');
        document.getElementById('screen-dispatch').classList.add('hidden');
        document.getElementById('screen-maintenance').classList.add('hidden');
        document.getElementById('screen-map-view').classList.add('hidden');
        if (document.getElementById('screen-analytics-fleet-utilization')) {
            document.getElementById('screen-analytics-fleet-utilization').classList.add('hidden');
        }
        if (document.getElementById('screen-analytics-route-performance')) {
            document.getElementById('screen-analytics-route-performance').classList.add('hidden');
        }
        if (document.getElementById('screen-analytics-driver-performance')) {
            document.getElementById('screen-analytics-driver-performance').classList.add('hidden');
        }
        document.getElementById('screen-drivers').classList.add('hidden');
        if (document.getElementById('screen-drivers-create')) {
            document.getElementById('screen-drivers-create').classList.add('hidden');
        }
        if (document.getElementById('screen-drivers-edit')) {
            document.getElementById('screen-drivers-edit').classList.add('hidden');
        }
        if (document.getElementById('screen-drivers-show')) {
            document.getElementById('screen-drivers-show').classList.add('hidden');
        }
        if (document.getElementById('screen-routes')) {
            document.getElementById('screen-routes').classList.add('hidden');
        }
        if (document.getElementById('screen-alerts')) {
            document.getElementById('screen-alerts').classList.add('hidden');
        }
        if (document.getElementById('screen-alerts-history')) {
            document.getElementById('screen-alerts-history').classList.add('hidden');
        }
        if (document.getElementById('screen-schedules-conflict')) {
            document.getElementById('screen-schedules-conflict').classList.add('hidden');
        }
        if (document.getElementById('screen-schedules-create')) {
            document.getElementById('screen-schedules-create').classList.add('hidden');
        }
        if (document.getElementById('screen-schedules-edit')) {
            document.getElementById('screen-schedules-edit').classList.add('hidden');
        }
        if (document.getElementById('screen-settings')) {
            document.getElementById('screen-settings').classList.add('hidden');
        }
        document.getElementById('screen-placeholder').classList.add('hidden');

        // Reset all navigation buttons
        const navButtons = document.querySelectorAll('[data-nav]');
        navButtons.forEach(btn => {
            btn.classList.remove('bg-[#0057BD]', 'text-white');
            btn.classList.add('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
        });

        // Set breadcrumb & active nav state
        const screenLabels = {
            'overview':    'Overview',
            'buses':       'Bus Management',
            'dispatch':    'Dispatch Management',
            'maintenance': 'Maintenance Records',
            'map-view':    'Live Fleet Map',
            'drivers':     'Driver List',
            'drivers-create': 'Register Municipal Driver',
            'drivers-edit': 'Edit Driver Details',
            'drivers-show': 'Driver Profile',
            'routes-stops': 'Routes & Stops',
            'routes-schedule': 'Schedules',
            'routes':      'Schedule & Routes',
            'alerts':      'Service Alerts',
            'alerts-history': 'Alert History Log',
            'schedules-conflict': 'Scheduling Conflict Check',
            'schedules-create': 'Create New Schedule',
            'schedules-edit':   'Edit Schedule',
            'analytics-fleet-utilization': 'Fleet Utilization',
            'analytics-route-performance': 'Route Performance',
            'analytics-driver-performance': 'Driver Performance',
            'analytics':   'Reports & Analytics',
            'settings':    'Settings',
        };

        const screenIcons = {
            'overview':    'ti-layout-dashboard',
            'buses':       'ti-bus',
            'dispatch':    'ti-send',
            'maintenance': 'ti-tool',
            'map-view':    'ti-map-pin',
            'drivers':     'ti-id',
            'drivers-create': 'ti-user-plus',
            'drivers-edit': 'ti-edit',
            'drivers-show': 'ti-id',
            'routes-stops': 'ti-route',
            'routes-schedule': 'ti-route',
            'routes':      'ti-route',
            'alerts':      'ti-bell-ringing',
            'alerts-history': 'ti-history',
            'schedules-conflict': 'ti-alert-triangle',
            'schedules-create': 'ti-plus',
            'schedules-edit':   'ti-edit',
            'analytics-fleet-utilization': 'ti-chart-bar',
            'analytics-route-performance': 'ti-chart-bar',
            'analytics-driver-performance': 'ti-chart-bar',
            'analytics':   'ti-chart-bar',
            'settings':    'ti-settings',
        };

        const activeNavBtn = document.querySelector(`[data-nav="${navHighlightName}"]`);
        const breadcrumbCurrent = document.getElementById('breadcrumb-current');
        const breadcrumbIcon = document.getElementById('breadcrumb-icon');

        if (activeNavBtn) {
            activeNavBtn.classList.remove('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
            activeNavBtn.classList.add('bg-[#0057BD]', 'text-white');
        }

        // Auto-expand/collapse dropdown sidebars
        updateSidebarDropdowns(navHighlightName);

        if (breadcrumbCurrent) {
            breadcrumbCurrent.textContent = screenLabels[navHighlightName] ?? activeNavBtn?.textContent.trim() ?? 'Dashboard';
        }

        if (breadcrumbIcon) {
            const iconClass = screenIcons[navHighlightName] ?? 'ti-layout-dashboard';
            breadcrumbIcon.className = `ti ${iconClass} text-sm text-slate-900`;
        }

        // Show target screen
        const targetScreen = document.getElementById(`screen-${parentScreenName}`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');

            // If target is buses, reset the view to list mode
            if (parentScreenName === 'buses') {
                const listContainer = document.getElementById('buses-list-container');
                const formContainer = document.getElementById('buses-form-container');
                if (listContainer) listContainer.classList.remove('hidden');
                if (formContainer) formContainer.classList.add('hidden');
            }
            
            // If target is live map, initialize Leaflet Map correctly
            if (parentScreenName === 'map-view') {
                setTimeout(() => {
                    initLiveFleetMap();
                }, 50);
            }

            // If target is reports & analytics, initialize ChartJS correctly
            if (parentScreenName.startsWith('analytics-')) {
                setTimeout(() => {
                    initAnalyticsDashboard();
                }, 100);
            }

            // If target is drivers, render table
            if (parentScreenName === 'drivers') {
                setTimeout(() => {
                    if (typeof renderDriversTable === 'function') {
                        renderDriversTable(typeof DRIVERS_DATA !== 'undefined' ? DRIVERS_DATA : []);
                    }
                }, 50);
            }

            // If target is routes, render schedules/routes
            if (parentScreenName === 'routes') {
                setTimeout(() => {
                    if (typeof initRoutesDashboard === 'function') {
                        initRoutesDashboard();
                    }
                }, 50);
            }

            // If target is alerts, render alerts
            if (parentScreenName === 'alerts') {
                setTimeout(() => {
                    initAlertsDashboard();
                }, 50);
            }

            // If target is settings, render settings
            if (parentScreenName === 'settings') {
                setTimeout(() => {
                    if (typeof initSettingsDashboard === 'function') {
                        initSettingsDashboard();
                    }
                }, 50);
            }

            // If target is alerts history, render history table
            if (parentScreenName === 'alerts-history') {
                setTimeout(() => {
                    loadDatabaseAlertsData().then(() => {
                        renderHistoryTable();
                    });
                }, 50);
            }

            // If target is schedules conflict check
            if (parentScreenName === 'schedules-conflict') {
                setTimeout(() => {
                    loadDatabaseResources();
                }, 50);
            }

            // If target is schedules create
            if (parentScreenName === 'schedules-create') {
                setTimeout(() => {
                    loadSchedulesAndResourcePools().then(() => {
                        onRouteSelectChange();
                    });
                }, 50);
            }

            // If target is schedules edit
            if (parentScreenName === 'schedules-edit') {
                setTimeout(() => {
                    loadSchedulesAndResourcePoolsForEditPage().then(() => {
                        onEditPageRouteSelectChange();
                    });
                }, 50);
            }
        } else {
            // Display placeholder for unsupported dynamic links
            const placeholderScreen = document.getElementById('screen-placeholder');
            const placeholderTitle = document.getElementById('placeholder-title');
            const placeholderIcon = document.getElementById('placeholder-icon');

            placeholderScreen.classList.remove('hidden');

            const label = screenLabels[navHighlightName] ?? (activeNavBtn ? activeNavBtn.textContent.trim() : 'Ops Module');
            if (breadcrumbCurrent) breadcrumbCurrent.textContent = label;
            placeholderTitle.textContent = label;
            
            // Map icons for visual polish
            if (parentScreenName === 'map-view') placeholderIcon.className = "ti ti-map-pin text-3xl";
            else if (parentScreenName === 'drivers') placeholderIcon.className = "ti ti-id text-3xl";
            else if (parentScreenName === 'routes') placeholderIcon.className = "ti ti-route text-3xl";
            else if (parentScreenName === 'alerts') placeholderIcon.className = "ti ti-bell-ringing text-3xl";
            else if (parentScreenName.startsWith('analytics-')) placeholderIcon.className = "ti ti-chart-bar text-3xl";
            else placeholderIcon.className = "ti ti-settings text-3xl";
        }

        // Close sidebar drawer on mobile after clicks
        const sidebar = document.getElementById('sidebar');
        if (!sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    }

    // Auto-activate tab from hash immediately and on load to prevent page flashing
    function checkHashRoute() {
        let hash = window.location.hash.replace('#', '') || 'overview';
        let param = null;

        if (hash.startsWith('drivers-edit-')) {
            param = hash.replace('drivers-edit-', '');
            hash = 'drivers-edit';
        } else if (hash.startsWith('drivers-show-')) {
            param = hash.replace('drivers-show-', '');
            hash = 'drivers-show';
        } else if (hash.startsWith('schedules-edit-')) {
            param = hash.replace('schedules-edit-', '');
            hash = 'schedules-edit';
        }

        if (typeof switchScreen === 'function') {
            if (hash === 'drivers-edit' && param) {
                if (typeof openDriversEditScreen === 'function') {
                    openDriversEditScreen(parseInt(param));
                }
            } else if (hash === 'drivers-show' && param) {
                if (typeof openDriversShowScreen === 'function') {
                    openDriversShowScreen(parseInt(param));
                }
            } else if (hash === 'schedules-edit' && param) {
                if (typeof openEditScheduleForm === 'function') {
                    openEditScheduleForm(parseInt(param));
                }
            } else if (hash === 'drivers-create') {
                if (typeof openDriversCreateScreen === 'function') {
                    openDriversCreateScreen();
                }
            } else if (hash === 'routes-stops') {
                if (typeof switchRoutesTab === 'function') {
                    switchRoutesTab('stops');
                } else {
                    window.activeRoutesTab = 'stops';
                }
            } else if (hash === 'routes-schedule') {
                if (typeof switchRoutesTab === 'function') {
                    switchRoutesTab('schedule');
                } else {
                    window.activeRoutesTab = 'schedule';
                }
            }
            switchScreen(hash);
        }
    }

    checkHashRoute();
    window.addEventListener('load', checkHashRoute);
    window.addEventListener('hashchange', checkHashRoute);
