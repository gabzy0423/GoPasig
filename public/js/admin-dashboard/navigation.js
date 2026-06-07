    // Toggle Responsive Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Switch Screens seamlessly
    function switchScreen(screenName) {
        // Hide all screens
        document.getElementById('screen-overview').classList.add('hidden');
        document.getElementById('screen-buses').classList.add('hidden');
        document.getElementById('screen-dispatch').classList.add('hidden');
        document.getElementById('screen-maintenance').classList.add('hidden');
        document.getElementById('screen-map-view').classList.add('hidden');
        document.getElementById('screen-analytics').classList.add('hidden');
        document.getElementById('screen-drivers').classList.add('hidden');
        if (document.getElementById('screen-routes')) {
            document.getElementById('screen-routes').classList.add('hidden');
        }
        if (document.getElementById('screen-alerts')) {
            document.getElementById('screen-alerts').classList.add('hidden');
        }
        document.getElementById('screen-placeholder').classList.add('hidden');

        // Reset all navigation buttons
        const navButtons = document.querySelectorAll('[data-nav]');
        navButtons.forEach(btn => {
            btn.classList.remove('bg-[#0057BD]', 'text-white');
            btn.classList.add('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
        });

        // Set page header title & active state
        const activeNavBtn = document.querySelector(`[data-nav="${screenName}"]`);
        const pageTitle = document.getElementById('page-title');

        if (activeNavBtn) {
            activeNavBtn.classList.remove('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
            activeNavBtn.classList.add('bg-[#0057BD]', 'text-white');
            pageTitle.textContent = activeNavBtn.textContent.trim();
        }

        // Show target screen
        const targetScreen = document.getElementById(`screen-${screenName}`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');
            
            // If target is live map, initialize Leaflet Map correctly
            if (screenName === 'map-view') {
                setTimeout(() => {
                    initLiveFleetMap();
                }, 50);
            }

            // If target is reports & analytics, initialize ChartJS correctly
            if (screenName === 'analytics') {
                setTimeout(() => {
                    initAnalyticsDashboard();
                }, 50);
            }

            // If target is drivers, render table
            if (screenName === 'drivers') {
                setTimeout(() => {
                    if (typeof renderDriversTable === 'function') {
                        renderDriversTable(typeof DRIVERS_DATA !== 'undefined' ? DRIVERS_DATA : []);
                    }
                }, 50);
            }

            // If target is routes, render schedules/routes
            if (screenName === 'routes') {
                setTimeout(() => {
                    if (typeof initRoutesDashboard === 'function') {
                        initRoutesDashboard();
                    }
                }, 50);
            }

            // If target is alerts, render alerts
            if (screenName === 'alerts') {
                setTimeout(() => {
                    if (typeof initAlertsDashboard === 'function') {
                        initAlertsDashboard();
                    }
                }, 50);
            }
        } else {
            // Display placeholder for unsupported dynamic links
            const placeholderScreen = document.getElementById('screen-placeholder');
            const placeholderTitle = document.getElementById('placeholder-title');
            const placeholderIcon = document.getElementById('placeholder-icon');

            placeholderScreen.classList.remove('hidden');
            pageTitle.textContent = activeNavBtn ? activeNavBtn.textContent.trim() : "Ops Module";
            placeholderTitle.textContent = activeNavBtn ? activeNavBtn.textContent.trim() : "Transit Ops Module";
            
            // Map icons for visual polish
            if (screenName === 'map-view') placeholderIcon.className = "ti ti-map-pin text-3xl";
            else if (screenName === 'drivers') placeholderIcon.className = "ti ti-id text-3xl";
            else if (screenName === 'routes') placeholderIcon.className = "ti ti-route text-3xl";
            else if (screenName === 'alerts') placeholderIcon.className = "ti ti-bell-ringing text-3xl";
            else if (screenName === 'analytics') placeholderIcon.className = "ti ti-chart-bar text-3xl";
            else placeholderIcon.className = "ti ti-settings text-3xl";
        }

        // Close sidebar drawer on mobile after clicks
        const sidebar = document.getElementById('sidebar');
        if (!sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    }
