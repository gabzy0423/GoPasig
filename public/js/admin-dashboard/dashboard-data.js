// Dynamic Database-Driven Fleet Tracking System

// Globals with fallback placeholders (populated dynamically from MySQL)
const fleetData = [];
const tripsData = [];

const statusColors = {
    Active: '#003F87',
    Delayed: '#BA7517',
    Breakdown: '#E24B4A',
    Idle: '#888780'
};

const statusBadgeColors = {
    Active: 'bg-[#E8F4E0] text-[#639922]',
    Delayed: 'bg-[#FEF7ED] text-[#BA7517]',
    Breakdown: 'bg-[#FDF2F2] text-[#E24B4A]',
    Idle: 'bg-slate-50 border border-slate-200 text-slate-500'
};

const routeNames = {};
const routeColors = {};
const routesDataDb = [];

// Leaflet global instances
let liveMap = null;
let mapMarkersMap = {}; // mapping bus.id to leaflet markers
let mapPolylinesMap = {}; // mapping route letter to leaflet polylines
let mapStopCircles = []; // stop circles array
let activeRouteFilter = 'all';
let activeStatusFilter = 'all';
let mapUpdateSeconds = 5;
let isDatabaseDataLoaded = false;

// Dynamic loader from MySQL Database API
async function loadDatabaseFleetData() {
    try {
        const url = (window.GoPasigConfig && window.GoPasigConfig.fleetDataUrl) ? window.GoPasigConfig.fleetDataUrl : '/admin/api/fleet-data';
        const response = await fetch(url);
        const data = await response.json();

        console.log("API RAW RESPONSE routes:", data.routes ? data.routes.length : 'undefined');
        console.log("API RAW RESPONSE buses:", data.buses ? data.buses.length : 'undefined');

        // 1. Populate Fleet data
        fleetData.length = 0; // clear existing
        data.buses.forEach(bus => {
            fleetData.push({
                id: bus.id,
                plate: bus.plate_number,
                driver: bus.driver_name || "Unassigned",
                route: bus.route_id ? bus.route_id.toString() : "None",
                speed: bus.speed,
                passengers: bus.passengers,
                capacity: bus.capacity,
                nextStop: bus.next_stop || "None",
                eta: bus.eta,
                status: bus.status.charAt(0).toUpperCase() + bus.status.slice(1), // capitalize status
                lat: parseFloat(bus.lat),
                lng: parseFloat(bus.lng)
            });
        });

        // 2. Populate Routes mapping configuration dynamically
        for (let key in routeNames) delete routeNames[key];
        for (let key in routeColors) delete routeColors[key];
        routesDataDb.length = 0; // clear existing

        data.routes.forEach(route => {
            routesDataDb.push(route);
            routeNames[route.id.toString()] = `${route.name} | ${route.description}`;


            routeColors[route.id.toString()] = route.color || '#003F87';
        });

        // 3. Populate Trips logs dynamically
        tripsData.length = 0;
        if (data.trips) {
            data.trips.forEach(trip => {
                const logTime = new Date(trip.created_at || trip.started_at);
                const timeString = logTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
                const routeShort = trip.route ? trip.route.name : 'Route 1';

                let driverShort = 'Unassigned';
                if (trip.driver) {
                    driverShort = `${trip.driver.first_name.charAt(0)}. ${trip.driver.last_name}`;
                }

                let statusLabel = 'Ongoing';
                if (trip.status === 'completed') statusLabel = 'Completed';
                else if (trip.status === 'cancelled') statusLabel = 'Cancelled';
                else if (trip.status === 'ongoing') statusLabel = 'Active';

                tripsData.push({
                    time: timeString,
                    route: routeShort,
                    driver: driverShort,
                    status: statusLabel
                });
            });
        }

        isDatabaseDataLoaded = true;
        console.log("MySQL Database fleet records loaded dynamically!");

        // Trigger visual rendering updates if overview map is initialized
        if (typeof initOverviewMap === 'function' && document.getElementById('overview-map')) {
            initOverviewMap();
        }
        if (typeof renderOverviewBuses === 'function') {
            renderOverviewBuses();
        }
        if (typeof updateOverviewDashboard === 'function') {
            updateOverviewDashboard();
        }
        if (typeof renderBusesTable === 'function') {
            renderBusesTable();
            updateBusSummaryStats();
        }
        if (liveMap !== null && typeof renderMapMarkers === 'function') {
            renderMapMarkers();
            updateFleetSidebarList();
            updateFleetSummaryStats();
        }
        if (typeof syncRoutesWithDatabase === 'function') {
            syncRoutesWithDatabase();
        }
        if (typeof activeRoutesTab !== 'undefined') {
            if (activeRoutesTab === 'stops' && typeof renderRoutesTab === 'function') {
                renderRoutesTab();
            } else if (activeRoutesTab === 'schedule') {
                if (typeof renderScheduleGrid === 'function') {
                    renderScheduleGrid();
                }
                if (typeof renderUpcomingTrips === 'function') {
                    renderUpcomingTrips();
                }
            }
        }
    } catch (error) {
        console.error("Failed to load dynamic database fleet data:", error);
    }
}

// Fetch database records immediately on load
loadDatabaseFleetData();
