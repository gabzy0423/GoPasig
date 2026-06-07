/**
 * Navigation and Screen Switching Manager for Fleet Dashboard
 * Handles the SPA (Single Page Application) screen transitions
 * and manages direct deep-linking via query parameters.
 */

// Switch Screens seamlessly inside the Fleet Dashboard
function switchScreen(screenName) {
    // Hide all screens
    const screens = [
        'overview', 'monitor', 'utilization', 'drivers',
        'routes', 'schedule', 'incidents', 'maintenance',
        'announcements', 'analytics', 'dispatch-intelligence', 'placeholder'
    ];
    screens.forEach(s => {
        const el = document.getElementById(`screen-${s}`);
        if (el) {
            el.classList.add('hidden');
            el.style.display = 'none';
        }
    });

    // Reset all navigation buttons
    const navButtons = document.querySelectorAll('[data-nav]');
    navButtons.forEach(btn => {
        btn.classList.remove('bg-white/12', 'text-white');
        btn.classList.add('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
    });

    // Set page header title & active state
    const activeNavBtn = document.querySelector(`[data-nav="${screenName}"]`);
    const pageTitle = document.getElementById('page-title');

    if (activeNavBtn) {
        activeNavBtn.classList.remove('text-white/70', 'hover:text-white', 'hover:bg-white/[0.04]');
        activeNavBtn.classList.add('bg-white/12', 'text-white');
        if (pageTitle) {
            pageTitle.textContent = 'Fleet Ops / ' + activeNavBtn.textContent.trim();
        }
    }

    // Show target screen
    const targetScreen = document.getElementById(`screen-${screenName}`);
    if (targetScreen) {
        targetScreen.classList.remove('hidden');
        targetScreen.style.display = screenName === 'overview' ? 'block' : '';

        // --- Direct init calls per screen (admin pattern) ---

        // Analytics: init ECharts
        if (screenName === 'analytics') {
            setTimeout(() => {
                if (typeof initAnalyticsCharts === 'function') {
                    initAnalyticsCharts();
                }
            }, 50);
        }

        // Routes: init ECharts
        if (screenName === 'routes') {
            setTimeout(() => {
                if (typeof initRouteCharts === 'function') {
                    initRouteCharts();
                }
            }, 50);
        }

        // Schedule: init ECharts
        if (screenName === 'schedule') {
            setTimeout(() => {
                if (typeof initComplianceCharts === 'function') {
                    initComplianceCharts();
                }
            }, 50);
        }

        // Drivers: init ECharts
        if (screenName === 'drivers') {
            setTimeout(() => {
                if (typeof initDriverCharts === 'function') {
                    initDriverCharts();
                }
            }, 50);
        }

        // Live map: invalidate Leaflet size
        if (screenName === 'monitor' && typeof map !== 'undefined') {
            setTimeout(() => {
                map.invalidateSize();
            }, 50);
        }

        // Dispatch a global resize event to force charts to recalculate dimensions
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Intercept sidebar link clicks for SPA feel on the dashboard
    const navLinks = document.querySelectorAll('[data-nav]');
    navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            const screenName = this.getAttribute('data-nav');
            const targetScreen = document.getElementById(`screen-${screenName}`);
            if (targetScreen) {
                e.preventDefault();
                switchScreen(screenName);
            }
        });
    });

    // Handle direct subfolder query parameters focus and deep-linking safely
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    const focusBus = urlParams.get('bus');
    const focusRoute = urlParams.get('route');
    
    if (activeTab && activeTab.trim() !== '') {
        switchScreen(activeTab);
    } else if ((focusBus && focusBus !== 'undefined' && focusBus !== 'null' && focusBus.trim() !== '') || 
        (focusRoute && focusRoute !== 'undefined' && focusRoute !== 'null' && focusRoute.trim() !== '')) {
        switchScreen('monitor');
    } else {
        switchScreen('overview');
    }
});
