// Dynamic Database-Driven Fleet Tracking System

// Globals with fallback placeholders (populated dynamically from MySQL)
const fleetData = [];
const tripsData = [];
const dispatchQueueData = [];
const SPEED_DISPLAY_DEADBAND_KMH = 0.5;

function normalizeDisplaySpeedKmh(speedKmh, movementState = null) {
    if (String(movementState || '').toUpperCase() === 'STATIONARY') {
        return 0;
    }

    const roundedSpeed = Math.round((Number(speedKmh) || 0) * 10) / 10;
    return roundedSpeed < SPEED_DISPLAY_DEADBAND_KMH ? 0 : roundedSpeed;
}

const statusColors = {
    Active: '#003F87',
    Moving: '#003F87',
    Stopped: '#888780',
    Delayed: '#BA7517',
    Breakdown: '#E24B4A',
    Offline: '#E24B4A',
    Idle: '#888780'
};

const statusBadgeColors = {
    Active: 'bg-[#E8F4E0] text-[#639922]',
    Moving: 'bg-[#E8F4E0] text-[#639922]',
    Stopped: 'bg-slate-50 border border-slate-200 text-slate-500',
    Delayed: 'bg-[#FEF7ED] text-[#BA7517]',
    Breakdown: 'bg-[#FDF2F2] text-[#E24B4A]',
    Offline: 'bg-[#FDF2F2] text-[#E24B4A]',
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
                speed: normalizeDisplaySpeedKmh(bus.speed_kmh ?? bus.speed ?? 0, bus.movement_state ?? null),
                speedMps: bus.speed_mps ?? null,
                speedKmh: bus.speed_kmh ?? bus.speed ?? 0,
                speedUnit: bus.speed_unit || 'm/s',
                heading: bus.heading ?? null,
                displayHeading: bus.display_heading ?? null,
                headingSource: bus.heading_source ?? 'unavailable',
                headingUpdatedAt: bus.heading_updated_at ?? null,
                movementState: bus.movement_state ?? null,
                movementConfidence: bus.movement_confidence ?? null,
                movementReason: bus.movement_reason ?? null,
                movementStateUpdatedAt: bus.movement_state_updated_at ?? null,
                stationaryDurationSeconds: bus.stationary_duration_seconds ?? null,
                gpsQualityState: bus.gps_quality_state ?? 'UNKNOWN',
                gpsQualityReason: bus.gps_quality_reason ?? null,
                gpsFixAgeSeconds: bus.gps_fix_age_seconds ?? null,
                lastGpsFixAt: bus.last_gps_fix_at ?? null,
                operationalStatus: bus.operational_status ?? bus.status ?? null,
                passengers: bus.passengers,
                capacity: bus.capacity,
                nextStop: bus.next_stop || "None",
                eta: bus.eta,
                status: (bus.operational_status || bus.status || 'unknown').charAt(0).toUpperCase() + (bus.operational_status || bus.status || 'unknown').slice(1), // capitalize status
                lat: parseFloat(bus.lat),
                lng: parseFloat(bus.lng),
                has_active_trip: !!bus.has_active_trip,
                trip_id: bus.trip_id || null,
                coordinate_source: bus.coordinate_source || 'bus_fallback',
                has_live_telemetry: !!bus.has_live_telemetry,
                state_mismatch: !!bus.state_mismatch,
                state_mismatch_details: bus.state_mismatch_details || null,
                last_gps_at: bus.last_gps_at || null,
                fleet_number: bus.fleet_number,
                vin: bus.vin,
                manufacturer: bus.manufacturer,
                model: bus.model,
                year_model: bus.year_model,
                battery_capacity_kwh: bus.battery_capacity_kwh,
                max_charging_power_kw: bus.max_charging_power_kw,
                charging_port_type: bus.charging_port_type,
                created_at: bus.created_at,
                updated_at: bus.updated_at,
                purchase_date: bus.purchase_date,
                supplier: bus.supplier,
                warranty_expiry: bus.warranty_expiry,
                serial_number: bus.serial_number,
                acquisition_cost: bus.acquisition_cost
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
                    status: statusLabel,
                    busPlate: trip.bus ? trip.bus.plate_number : 'Unknown',
                    timestamp: logTime
                });
            });
        }

        await loadTodayDispatchQueue();

        isDatabaseDataLoaded = true;
        console.log("MySQL Database fleet records loaded dynamically!");

        // Trigger visual rendering updates if overview map is initialized
        if (typeof initOverviewMap === 'function' && document.getElementById('overview-map')) {
            initOverviewMap();
        }
        if (typeof renderOverviewPolylines === 'function' && overviewMapInstance !== null) {
            renderOverviewPolylines();
        }
        if (typeof renderOverviewStops === 'function' && overviewMapInstance !== null) {
            renderOverviewStops();
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
            if (typeof updateRoutePillsCounts === 'function') {
                updateRoutePillsCounts();
            }
            if (typeof updateRecentActivityUI === 'function') {
                updateRecentActivityUI();
            }
            if (typeof renderMapPolylines === 'function') {
                renderMapPolylines();
            }
            if (typeof renderMapStops === 'function') {
                renderMapStops();
            }
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

async function loadTodayDispatchQueue() {
    const url = window.GoPasigConfig && window.GoPasigConfig.dispatchQueueTodayUrl
        ? window.GoPasigConfig.dispatchQueueTodayUrl
        : '/admin/api/schedules/dispatch-queue/today';

    const response = await fetch(url);
    const data = await response.json();

    dispatchQueueData.length = 0;
    if (response.ok && data.success) {
        data.dispatches.forEach(dispatch => dispatchQueueData.push(dispatch));
    }
}

// Fetch database records immediately on load
loadDatabaseFleetData();








