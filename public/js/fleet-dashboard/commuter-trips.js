/**
 * GoPasig Fleet Ops - Commuter Trips Javascript Controller
 * Handles dynamic fetching, search/filtering, status transitions rendering, and pagination.
 */

let tripsCurrentPage = 1;

async function fetchCommuterTrips(page = 1) {
    tripsCurrentPage = page;
    const search = document.getElementById('trips-search-input')?.value || '';
    const routeId = document.getElementById('trips-filter-route')?.value || 'all';
    const status = document.getElementById('trips-filter-status')?.value || 'all';

    try {
        const queryParams = new URLSearchParams({
            page: page,
            search: search,
            route_id: routeId,
            status: status
        });

        const response = await fetch(`/fleet/api/commuter-trips?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch commuter trips');
        const data = await response.json();

        renderTripsTableDOM(data.data);
        renderTripsPaginationDOM(data);
        
        // Update count badge
        const badge = document.getElementById('trips-total-badge');
        if (badge) {
            badge.innerText = `${data.total} entries`;
        }
    } catch (error) {
        console.error('Error loading commuter trips data:', error);
    }
}

function renderTripsTableDOM(trips) {
    const tbody = document.getElementById('trips-table-body');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!trips || trips.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-12 text-center bg-slate-50/50">
                    <i class="ti ti-clipboard-off text-[36px] text-slate-300"></i>
                    <p class="text-xs font-bold text-slate-500 mt-2">No commuter trips found</p>
                    <p class="text-[11px] text-slate-400 mt-1">Make sure you have active simulated trips or adjustment filters.</p>
                </td>
            </tr>
        `;
        return;
    }

    const statusClasses = {
        'WAITING': 'bg-[#FAEEDA] text-[#854F0B] border-[#FAEEDA]',
        'ON_BUS': 'bg-[#E6F1FB] text-[#185FA5] border-[#E6F1FB]',
        'ARRIVED': 'bg-[#EAF3DE] text-[#3B6D11] border-[#EAF3DE]',
        'CANCELLED': 'bg-[#FCEBEB] text-[#A32D2D] border-[#FCEBEB]'
    };

    trips.forEach(trip => {
        const statusBadge = statusClasses[trip.status] || 'bg-slate-100 text-slate-600 border-slate-100';
        const routeName = trip.route
            ? `${trip.route.name}${trip.route_variant?.direction ? ' - ' + trip.route_variant.direction.charAt(0).toUpperCase() + trip.route_variant.direction.slice(1) : ''}`
            : `Route ${trip.route_id}`;
        
        // Eager loaded relations (Laravel Eloquent serializes camelCase relations to snake_case in array/json)
        const origin = trip.origin_route_variant_stop?.name
            || trip.origin_stop?.name
            || 'Unknown stop';
        const dest = trip.destination_route_variant_stop?.name
            || trip.destination_stop?.name
            || 'Unknown stop';
        
        // Build timestamp list
        let timesHtml = `<div class="space-y-0.5 text-[11px]">`;
        timesHtml += `<div>Created: <span class="font-mono text-slate-700">${formatTimestamp(trip.created_at)}</span></div>`;
        if (trip.boarded_at) {
            timesHtml += `<div class="text-[#185FA5]">Boarded: <span class="font-mono">${formatTimestamp(trip.boarded_at)}</span></div>`;
        }
        if (trip.arrived_at) {
            timesHtml += `<div class="text-[#3B6D11]">Arrived: <span class="font-mono">${formatTimestamp(trip.arrived_at)}</span></div>`;
        } else if (trip.status === 'CANCELLED') {
            timesHtml += `<div class="text-[#A32D2D]">Cancelled: <span class="font-mono">${formatTimestamp(trip.updated_at)}</span></div>`;
        }
        timesHtml += `</div>`;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.innerHTML = `
            <td class="py-3 px-3">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-slate-600 truncate max-w-[220px]" title="${trip.session_token}">${trip.session_token}</span>
                    <button onclick="copyToClipboard('${trip.session_token}')" class="text-slate-400 hover:text-[#003F87] transition cursor-pointer p-0.5" title="Copy Token">
                        <i class="ti ti-copy text-[14px]"></i>
                    </button>
                </div>
            </td>
            <td class="py-3 px-3 font-semibold text-[#001F44]">${routeName}</td>
            <td class="py-3 px-3 text-slate-700 truncate" title="${origin}">${origin}</td>
            <td class="py-3 px-3 text-slate-700 truncate" title="${dest}">${dest}</td>
            <td class="py-3 px-3">
                <span class="inline-flex rounded px-2 py-0.5 text-[11px] font-semibold border ${statusBadge}">${trip.status}</span>
            </td>
            <td class="py-3 px-3 text-slate-500 font-mono">${timesHtml}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderTripsPaginationDOM(meta) {
    const container = document.getElementById('trips-pagination');
    if (!container) return;

    if (meta.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    let linksHtml = `<div class="flex items-center gap-1.5">`;
    if (meta.current_page > 1) {
        linksHtml += `<button onclick="fetchCommuterTrips(${meta.current_page - 1})" class="px-3 py-1.5 rounded border border-black/10 bg-white hover:bg-slate-50 cursor-pointer font-medium">Prev</button>`;
    }
    
    for (let i = 1; i <= meta.last_page; i++) {
        const isCurrent = i === meta.current_page;
        const btnClass = isCurrent 
            ? 'bg-[#003F87] text-white border-[#003F87]' 
            : 'bg-white text-slate-600 hover:bg-slate-50 border-black/10';
        linksHtml += `<button onclick="fetchCommuterTrips(${i})" class="px-3 py-1.5 rounded border font-semibold cursor-pointer ${btnClass}">${i}</button>`;
    }

    if (meta.current_page < meta.last_page) {
        linksHtml += `<button onclick="fetchCommuterTrips(${meta.current_page + 1})" class="px-3 py-1.5 rounded border border-black/10 bg-white hover:bg-slate-50 cursor-pointer font-medium">Next</button>`;
    }
    linksHtml += `</div>`;

    container.innerHTML = `
        <div>Showing ${meta.from || 0} to ${meta.to || 0} of ${meta.total} entries</div>
        ${linksHtml}
    `;
}

function formatTimestamp(t) {
    if (!t) return 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â';
    try {
        const d = new Date(t);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const m = months[d.getMonth()];
        const day = d.getDate();
        let h = d.getHours();
        const min = d.getMinutes().toString().padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        h = h ? h : 12;
        return `${m} ${day}, ${h}:${min} ${ampm}`;
    } catch (e) {
        return t;
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        let toast = document.getElementById('session-copy-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'session-copy-toast';
            toast.className = 'fixed bottom-5 right-5 z-[100] px-4 py-2 bg-slate-800 text-white text-xs rounded-xl shadow-lg flex items-center gap-1.5 animate-fade-in-up';
            toast.innerHTML = '<i class="ti ti-circle-check text-emerald-400"></i> Token copied to clipboard!';
            document.body.appendChild(toast);
        }
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 2500);
    });
}

function resetTripsFiltersAction() {
    const search = document.getElementById('trips-search-input');
    const route = document.getElementById('trips-filter-route');
    const status = document.getElementById('trips-filter-status');

    if (search) search.value = '';
    if (route) route.value = 'all';
    if (status) status.value = 'all';

    fetchCommuterTrips(1);
}

// Setup polling and input events
let fleetCommuterTripsModuleInitialized = false;
let fleetCommuterTripsPollingRegistration = null;

function initFleetCommuterTripsModule() {
    if (fleetCommuterTripsModuleInitialized || !document.getElementById('trips-search-input')) return;
    fleetCommuterTripsModuleInitialized = true;


    const search = document.getElementById('trips-search-input');
    const route = document.getElementById('trips-filter-route');
    const status = document.getElementById('trips-filter-status');

    let debounceTimeout = null;
    search?.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => fetchCommuterTrips(1), 350);
    });

    route?.addEventListener('change', () => fetchCommuterTrips(1));
    status?.addEventListener('change', () => fetchCommuterTrips(1));

    if (!fleetCommuterTripsPollingRegistration) {
        const refreshTripsWhenVisible = () => {
            const tripsScreen = document.getElementById('screen-commuter-trips');
            if (tripsScreen && !tripsScreen.classList.contains('hidden')) {
                return fetchCommuterTrips(tripsCurrentPage);
            }

            return undefined;
        };

        fleetCommuterTripsPollingRegistration = window.GoPasigFleetModules?.registerPoller
            ? window.GoPasigFleetModules.registerPoller('commuter-trips', 'trip-data', refreshTripsWhenVisible, 10000)
            : setInterval(refreshTripsWhenVisible, 10000);
    }
}

window.initFleetCommuterTripsModule = initFleetCommuterTripsModule;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFleetCommuterTripsModule, { once: true });
} else {
    initFleetCommuterTripsModule();
}
