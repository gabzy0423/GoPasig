/**
 * GoPasig Fleet Ops - Drivers & Routes Performance JS Controller
 * Handles ECharts rendering, AJAX filters, CSV exports, detail drawers, local paginations, and tables sorting.
 */

// Global Config & State
window.FleetPerformanceConfig = {
    driversUrl: '/fleet/api/drivers-data',
    driverDetailsUrl: '/fleet/api/drivers-details',
    driverMessageUrl: '/fleet/api/drivers-message',
    driverExportUrl: '/fleet/api/drivers-export',
    routesUrl: '/fleet/api/routes-data',
    routeExportUrl: '/fleet/api/routes-export',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// ECharts References
let driverScoreChart = null;
let headwayChart = null;
let tripDurationChart = null;

// Table Sorting & Pagination State
let currentDriverSortCol = '';
let currentDriverSortDir = 'asc';
let currentStopSortCol = '';
let currentStopSortDir = 'asc';

let allStopsData = [];
let stopCurrentPage = 1;
const stopPerPage = 10;

// Shared Helper: Format Numbers
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

function escapeMarkup(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function safeRouteColor(value) {
    return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? value : '#64748b';
}

function formatScore(score) {
    return score === null || score === undefined ? 'No data' : score;
}

function scorePillClasses(score) {
    if (score === null || score === undefined) return 'bg-slate-100 text-slate-400';
    return score >= 85
        ? 'bg-[#EAF3DE] text-[#3B6D11]'
        : (score >= 70 ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-[#FCEBEB] text-[#A32D2D]');
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// 1. DRIVER PERFORMANCE MODULE
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

async function fetchDriversData() {
    const startDate = document.getElementById('driver-start-date')?.value || '';
    const endDate = document.getElementById('driver-end-date')?.value || '';
    const routeId = document.getElementById('driver-route-id')?.value || 'all';
    const status = document.getElementById('driver-status')?.value || 'all';
    const search = document.getElementById('driver-search-input')?.value || '';

    try {
        const queryParams = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            route_id: routeId,
            status: status,
            search: search
        });

        const response = await fetch(`${window.FleetPerformanceConfig.driversUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch driver data');
        const data = await response.json();

        updateDriverMetricsDOM(data.driverMetrics);
        updateTopDriversDOM(data.topDrivers);
        updateDriverTableDOM(data.driverLogs);
        updateScoreDistributionChart(data.driverLogs);
    } catch (e) {
        console.error('Error fetching drivers data:', e);
    }
}

function updateDriverMetricsDOM(metrics) {
    const totalEl = document.getElementById('metric-total-drivers');
    const onDutyEl = document.getElementById('metric-on-duty-today');
    const avgScoreEl = document.getElementById('metric-avg-score');
    const incidentsEl = document.getElementById('metric-total-incidents');
    const avgTripsEl = document.getElementById('metric-avg-trips');

    if (totalEl) totalEl.innerText = metrics.total_drivers;
    if (onDutyEl) onDutyEl.innerText = metrics.drivers_with_trips ?? metrics.on_duty_today;
    if (avgScoreEl) avgScoreEl.innerText = formatScore(metrics.avg_performance_score);
    if (incidentsEl) incidentsEl.innerText = metrics.incidents_this_period;
    if (avgTripsEl) avgTripsEl.innerText = metrics.avg_trips_per_driver;
}

function updateTopDriversDOM(topDrivers) {
    const list = document.getElementById('top-drivers-list');
    if (!list) return;

    list.innerHTML = '';
    if (topDrivers.length === 0) {
        list.innerHTML = '<div class="text-center py-6 text-slate-400">No driver records found.</div>';
        return;
    }

    topDrivers.forEach(top => {
        let avatarClasses = 'bg-purple-200 text-purple-800';
        if (top.rank === 1) avatarClasses = 'bg-blue-200 text-blue-800';
        else if (top.rank === 2) avatarClasses = 'bg-teal-200 text-teal-800';
        else if (top.rank === 3) avatarClasses = 'bg-amber-200 text-amber-800';
        else if (top.rank === 4) avatarClasses = 'bg-orange-200 text-orange-800';

        const rowStyle = top.rank === 1 ? 'border-l-[3px] border-[#003F87] bg-[#E6F1FB] pl-3' : '';
        const scorePill = scorePillClasses(top.performance_score);

        const div = document.createElement('div');
        div.className = `flex items-center justify-between py-2.5 transition-all duration-150 ${rowStyle}`;
        div.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="text-[18px] font-medium text-slate-400 w-5">${top.rank}</span>
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold ${avatarClasses}">
                    ${top.initials}
                </div>
                <div class="flex flex-col">
                    <span class="text-[14px] font-medium text-[#001F44]">${top.driver_name}</span>
                    <span class="flex items-center gap-1 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full inline-block" style="background-color: ${top.route_color}"></span>
                        <span class="text-slate-400 text-[11px] font-medium">${top.assigned_route}</span>
                    </span>
                </div>
            </div>
            <div class="flex flex-col items-end gap-1">
                <span class="px-2 py-0.5 rounded text-[13px] font-semibold tracking-wide ${scorePill}">${formatScore(top.performance_score)}</span>
                <span class="text-[11px] text-slate-400 font-medium">${top.trips_run} trips</span>
            </div>
        `;
        list.appendChild(div);
    });
}

function updateDriverTableDOM(drivers) {
    const tbody = document.getElementById('driver-table-body');
    const wrapper = document.getElementById('driver-table-wrapper');
    const countBadge = document.getElementById('driver-records-count');
    const emptyState = document.getElementById('driver-table-empty');

    if (!tbody) return;

    if (countBadge) countBadge.innerText = `${drivers.length} records`;

    tbody.innerHTML = '';

    if (drivers.length === 0) {
        if (wrapper) wrapper.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        return;
    }

    if (wrapper) wrapper.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');

    drivers.forEach(row => {
        const statusBg = (row.status || '').toLowerCase() === 'on duty'
            ? 'bg-[#E1F5EE] text-[#0F6E56]'
            : ((row.status || '').toLowerCase() === 'off duty' ? 'bg-[#F1EFE8] text-[#5F5E5A]' : 'bg-[#FCEBEB] text-[#A32D2D]');

        const scoreBg = scorePillClasses(row.performance_score);

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 cursor-pointer transition-colors';
        tr.onclick = () => openDriverDrawer(row.driver_id);
        tr.setAttribute('data-driver_name', row.driver_name);
        tr.setAttribute('data-assigned_route', row.assigned_route);
        tr.setAttribute('data-status', row.status);
        tr.setAttribute('data-trips_run', row.trips_run);
        tr.setAttribute('data-recorded_boarded', row.recorded_boarded ?? row.total_passengers_moved ?? 0);
        tr.setAttribute('data-incidents', row.incidents);
        tr.setAttribute('data-avg_trip_time_minutes', row.avg_trip_time_minutes);
        tr.setAttribute('data-performance_score', row.performance_score ?? '');

        const incidentMarkup = row.incidents > 0
            ? `<span class="text-[#A32D2D] font-bold">${row.incidents}</span>`
            : '<span class="text-slate-400 font-medium">0</span>';

        tr.innerHTML = `
            <td class="py-3 px-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[11px] font-bold shrink-0">
                        ${row.initials}
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="font-medium text-[#001F44] truncate">${row.driver_name}</span>
                        <span class="text-[11px] text-slate-400 font-mono-custom">${row.emp_id}</span>
                    </div>
                </div>
            </td>
            <td class="py-3 px-4">
                <span class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background-color: ${row.route_color}"></span>
                    <span class="font-medium text-[#001F44]">${row.assigned_route}</span>
                </span>
            </td>
            <td class="py-3 px-4">
                <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide ${statusBg}">${row.status}</span>
            </td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${row.trips_run}</td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${formatNumber(row.recorded_boarded ?? row.total_passengers_moved ?? 0)}</td>
            <td class="py-3 px-4 text-center font-mono-custom">${incidentMarkup}</td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">
                ${row.avg_trip_time_minutes > 0 ? row.avg_trip_time_minutes + ' min' : 'No data'}
            </td>
            <td class="py-3 px-4 text-center">
                <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide ${scoreBg}">${formatScore(row.performance_score)}</span>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Reapply sorting state if set
    reapplyDriverTableSort();
}

function initDriverScoreChart(initialDrivers) {
    const container = document.getElementById('scoreDistributionChart');
    if (!container) return;

    driverScoreChart = echarts.init(container);

    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' }
        },
        grid: {
            left: '3%',
            right: '4%',
            top: '5%',
            bottom: '5%',
            containLabel: true
        },
        xAxis: {
            type: 'value',
            min: 0,
            max: 100,
            splitLine: { lineStyle: { color: '#f1f5f9' } },
            axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
        },
        yAxis: {
            type: 'category',
            data: [],
            axisTick: { show: false },
            axisLine: { show: false },
            axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
        },
        series: [{
            name: 'Performance Score',
            type: 'bar',
            data: [],
            itemStyle: { borderRadius: [0, 4, 4, 0] },
            markLine: {
                symbol: 'none',
                lineStyle: { type: 'dashed', color: '#64748b', width: 1.5 },
                label: {
                    show: true,
                    position: 'end',
                    formatter: 'Target (85)',
                    fontFamily: 'Plus Jakarta Sans',
                    fontWeight: 'bold',
                    color: '#64748b'
                },
                data: [{ xAxis: 85 }]
            }
        }]
    };

    driverScoreChart.setOption(option);
    updateScoreDistributionChart(initialDrivers);
}

function updateScoreDistributionChart(drivers) {
    if (!driverScoreChart) return;

    const scoredDrivers = drivers.filter(driver => driver.performance_score !== null && driver.performance_score !== undefined);
    const sorted = [...scoredDrivers].sort((a, b) => a.performance_score - b.performance_score);
    const labels = sorted.map(d => d.driver_name);
    const scores = sorted.map(d => d.performance_score);
    const colors = scores.map(s => s >= 85 ? '#639922' : s >= 70 ? '#BA7517' : '#E24B4A');

    driverScoreChart.setOption({
        title: {
            text: scoredDrivers.length ? '' : 'No score data',
            left: 'center',
            top: 'middle',
            textStyle: { fontFamily: 'Plus Jakarta Sans', color: '#94a3b8', fontSize: 13, fontWeight: 'normal' }
        },
        yAxis: { data: labels },
        series: [{
            data: scores.map((s, idx) => ({
                value: s,
                itemStyle: { color: colors[idx] }
            }))
        }]
    });
}

// Drawer loading via AJAX
async function openDriverDrawer(driverId) {
    const drawer = document.getElementById('driver-drawer');
    const drawerContent = document.getElementById('driver-drawer-content');
    const skeleton = document.getElementById('drawer-loading-skeleton');
    const mainBody = document.getElementById('drawer-details-body');
    const headerSection = document.getElementById('drawer-header-section');

    if (!drawer || !drawerContent) return;

    // Show drawer wrapper and clear previous data, show skeleton
    drawer.classList.remove('hidden');
    setTimeout(() => drawerContent.classList.remove('translate-x-full'), 10);

    if (skeleton) skeleton.classList.remove('hidden');
    if (mainBody) mainBody.classList.add('hidden');
    if (headerSection) headerSection.innerHTML = '';

    try {
        const startDate = document.getElementById('driver-start-date')?.value || '';
        const endDate = document.getElementById('driver-end-date')?.value || '';
        const queryParams = new URLSearchParams({ start_date: startDate, end_date: endDate });

        const response = await fetch(`${window.FleetPerformanceConfig.driverDetailsUrl}/${driverId}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to load driver details.');
        const data = await response.json();

        if (data.success) {
            renderDrawerData(data.selectedDriver, data.selectedDriverTrips, data.selectedDriverIncidents);
        }
    } catch (e) {
        console.error('Error loading driver drawer:', e);
        if (headerSection) headerSection.innerHTML = '<div class="text-red-500 font-bold p-4">Failed to load driver profile details.</div>';
    } finally {
        if (skeleton) skeleton.classList.add('hidden');
        if (mainBody) mainBody.classList.remove('hidden');
    }
}

function renderDrawerData(driver, trips, incidents) {
    const header = document.getElementById('drawer-header-section');
    const mainBody = document.getElementById('drawer-details-body');
    const msgBtn = document.getElementById('btn-message-driver');

    if (!header || !mainBody) return;

    const statusBg = driver.status.toLowerCase() === 'on duty'
        ? 'bg-[#E1F5EE] text-[#0F6E56]'
        : (driver.status.toLowerCase() === 'off duty' ? 'bg-[#F1EFE8] text-[#5F5E5A]' : 'bg-[#FCEBEB] text-[#A32D2D]');

    header.innerHTML = `
        <div class="w-12 h-12 rounded-full bg-blue-200 text-blue-800 flex items-center justify-center text-sm font-semibold">
            ${driver.initials}
        </div>
        <div class="flex flex-col min-w-0">
            <h3 class="text-[18px] font-medium text-[#001F44] truncate">${driver.driver_name}</h3>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span class="text-[11px] text-slate-400 font-mono-custom">${driver.emp_id}</span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full inline-block" style="background-color: ${driver.route_color}"></span>
                    <span class="text-slate-500 text-[11px] font-medium">${driver.assigned_route}</span>
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold tracking-wide ${statusBg}">${driver.status}</span>
            </div>
        </div>
    `;

    const hasScore = driver.performance_score !== null && driver.performance_score !== undefined;
    const scoreColor = !hasScore
        ? 'text-slate-400'
        : (driver.performance_score >= 85 ? 'text-[#3B6D11]' : (driver.performance_score >= 70 ? 'text-[#BA7517]' : 'text-[#A32D2D]'));
    const barFill = !hasScore
        ? 'bg-slate-300'
        : (driver.performance_score >= 85 ? 'bg-[#3B6D11]' : (driver.performance_score >= 70 ? 'bg-[#BA7517]' : 'bg-[#A32D2D]'));

    // Trips list
    let tripsHtml = '';
    if (trips.length === 0) {
        tripsHtml = '<p class="text-slate-400 text-xs italic">No Trip records for this driver in the selected period.</p>';
    } else {
        trips.forEach(t => {
            const badge = t.incident
                ? '<span class="mt-1 self-start rounded bg-[#FCEBEB] text-[#A32D2D] px-1.5 py-0.5 text-[10px] font-bold tracking-wide">Incident</span>'
                : '<span class="mt-1 self-start rounded bg-slate-100 text-slate-500 px-1.5 py-0.5 text-[10px] font-bold tracking-wide">No incident</span>';
            tripsHtml += `
                <div class="bg-white border border-slate-100 rounded p-2.5 flex flex-col gap-1 text-[13px] shadow-sm">
                    <div class="flex items-center justify-between font-semibold">
                        <span class="text-[#001F44]">${t.date}</span>
                        <span class="text-slate-600">${t.status}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400 text-[11px]">
                        <span>${t.route}</span>
                        <span>${t.duration > 0 ? t.duration + ' min' : 'No duration'}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-500 text-[11px]">
                        <span>${t.recorded_boarded} boarded / ${t.recorded_alighted} alighted</span>
                        <span>Peak ${t.peak_load}</span>
                    </div>
                    ${badge}
                </div>
            `;
        });
    }

    // Incidents list
    let incidentsHtml = '';
    if (incidents.length > 0) {
        let items = '';
        incidents.forEach(inc => {
            items += `
                <div class="bg-[#FCEBEB]/20 border-[0.5px] border-[#FCEBEB] rounded p-3 text-[13px]">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="font-semibold text-[#A32D2D]">${inc.type}</span>
                        <span class="text-slate-400 text-[11px]">${inc.date}</span>
                    </div>
                    <p class="text-slate-600 text-[12px] italic leading-normal">"${inc.description}"</p>
                </div>
            `;
        });

        incidentsHtml = `
            <div class="border-t border-slate-100"></div>
            <div>
                <h4 class="text-[14px] font-semibold text-[#001F44] mb-3">Incident log</h4>
                <div class="space-y-3">${items}</div>
            </div>
        `;
    }

    mainBody.innerHTML = `
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-slate-50 rounded p-3 flex flex-col justify-between">
                <span class="text-[11px] text-slate-500 uppercase font-semibold">Trips run</span>
                <span class="text-[20px] font-semibold text-[#001F44] mt-1">${driver.trips_run}</span>
            </div>
            <div class="bg-slate-50 rounded p-3 flex flex-col justify-between">
                <span class="text-[11px] text-slate-500 uppercase font-semibold">Recorded boarded</span>
                <span class="text-[20px] font-semibold text-[#001F44] mt-1">${formatNumber(driver.recorded_boarded ?? 0)}</span>
            </div>
            <div class="bg-slate-50 rounded p-3 flex flex-col justify-between">
                <span class="text-[11px] text-slate-500 uppercase font-semibold">Recorded alighted</span>
                <span class="text-[20px] font-semibold text-[#001F44] mt-1">${formatNumber(driver.recorded_alighted ?? 0)}</span>
            </div>
            <div class="bg-slate-50 rounded p-3 flex flex-col justify-between">
                <span class="text-[11px] text-slate-500 uppercase font-semibold">Incidents reported</span>
                <span class="text-[20px] font-semibold mt-1 ${driver.incidents > 0 ? 'text-[#A32D2D]' : 'text-slate-500'}">
                    ${driver.incidents}
                </span>
            </div>
            <div class="bg-slate-50 rounded p-3 flex flex-col justify-between">
                <span class="text-[11px] text-slate-500 uppercase font-semibold">Avg trip duration</span>
                <span class="text-[20px] font-semibold text-[#001F44] mt-1">
                    ${driver.avg_trip_time_minutes > 0 ? driver.avg_trip_time_minutes + ' min' : 'No data'}
                </span>
            </div>
        </div>

        <div class="border-t border-slate-100"></div>

        <div>
            <span class="text-[11px] text-slate-500 uppercase font-bold tracking-wider block">Performance score</span>
                <span class="text-[32px] font-semibold ${scoreColor} mt-1 block">${formatScore(driver.performance_score)}</span>
            <div class="relative w-full h-2 bg-slate-100 rounded-full mt-2">
                <div class="h-full rounded-full ${barFill}" style="width: ${hasScore ? Math.min(100, driver.performance_score) : 0}%"></div>
            </div>
            <span class="text-[11px] text-slate-400 mt-2 block">Actual trips run and qualifying incidents</span>
        </div>

        <div class="border-t border-slate-100"></div>

        <div>
            <h4 class="text-[14px] font-semibold text-[#001F44] mb-3">Recent trips</h4>
            <div class="space-y-2 max-h-[200px] overflow-y-auto pr-1">${tripsHtml}</div>
        </div>

        ${incidentsHtml}
    `;
}

function closeDriverDrawer() {
    const drawer = document.getElementById('driver-drawer');
    const drawerContent = document.getElementById('driver-drawer-content');
    if (!drawer || !drawerContent) return;

    drawerContent.classList.add('translate-x-full');
    setTimeout(() => drawer.classList.add('hidden'), 300);
}

async function messageDriverAction(driverId) {
    const message = await GoPasigUI.prompt("Enter the message to send to the driver:");
    if (!message || message.trim() === '') {
        return; // User cancelled or entered empty string
    }

    try {
        const response = await fetch(`${window.FleetPerformanceConfig.driverMessageUrl}/${driverId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetPerformanceConfig.csrfToken
            },
            body: JSON.stringify({ message: message.trim() })
        });
        const data = await response.json();
        if (response.ok && data.success) {
            // Flash a success message
            showNotification(data.message);
            closeDriverDrawer();
        }
    } catch (e) {
        console.error('Error messaging driver:', e);
    }
}

function showNotification(message) {
    let alertContainer = document.getElementById('driver-success-alert');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'driver-success-alert';
        alertContainer.className = 'fixed top-4 right-4 z-[60] p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-md animate-fade-in-up';
        document.body.appendChild(alertContainer);
    }
    alertContainer.innerHTML = `
        <i class="ti ti-circle-check text-[16px]"></i>
        <span>${message}</span>
    `;
    alertContainer.classList.remove('hidden');
    setTimeout(() => alertContainer.classList.add('hidden'), 4000);
}

// Client-side Driver Sorting
function sortDriverTable(columnName) {
    const tbody = document.getElementById('driver-table-body');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;

    if (currentDriverSortCol === columnName) {
        currentDriverSortDir = currentDriverSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentDriverSortCol = columnName;
        currentDriverSortDir = 'asc';
    }

    document.querySelectorAll('.sort-icon').forEach(i => {
        i.className = 'ti ti-arrows-sort text-slate-300 ml-1 sort-icon';
    });

    const activeIcon = document.getElementById(`sort-icon-${columnName}`);
    if (activeIcon) {
        activeIcon.className = currentDriverSortDir === 'asc'
            ? 'ti ti-arrow-narrow-up text-slate-700 ml-1 sort-icon'
            : 'ti ti-arrow-narrow-down text-slate-700 ml-1 sort-icon';
    }

    rows.sort((a, b) => {
        let valA = a.getAttribute(`data-${columnName}`);
        let valB = b.getAttribute(`data-${columnName}`);

        if (!isNaN(valA) && !isNaN(valB) && valA !== '' && valB !== '') {
            return currentDriverSortDir === 'asc' ? Number(valA) - Number(valB) : Number(valB) - Number(valA);
        }
        return currentDriverSortDir === 'asc'
            ? valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
            : valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
    });

    rows.forEach(row => tbody.appendChild(row));
}

function reapplyDriverTableSort() {
    if (currentDriverSortCol) {
        const col = currentDriverSortCol;
        const dir = currentDriverSortDir;
        currentDriverSortCol = '';
        currentDriverSortDir = '';
        if (dir === 'desc') {
            sortDriverTable(col);
            sortDriverTable(col);
        } else {
            sortDriverTable(col);
        }
    }
}

// CSV Export redirect with active filters
function exportDriverReport() {
    const startDate = document.getElementById('driver-start-date')?.value || '';
    const endDate = document.getElementById('driver-end-date')?.value || '';
    const routeId = document.getElementById('driver-route-id')?.value || 'all';
    const status = document.getElementById('driver-status')?.value || 'all';
    const search = document.getElementById('driver-search-input')?.value || '';

    const queryParams = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        route_id: routeId,
        status: status,
        search: search
    });

    window.location.href = `${window.FleetPerformanceConfig.driverExportUrl}?${queryParams.toString()}`;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// 2. ROUTE PERFORMANCE MODULE
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

let activeRouteTab = 'all';
async function fetchRoutesData() {
    const startDate = document.getElementById('route-start-date')?.value || '';
    const endDate = document.getElementById('route-end-date')?.value || '';

    try {
        const queryParams = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            route_id: activeRouteTab
        });

        const response = await fetch(`${window.FleetPerformanceConfig.routesUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch routes data');
        const data = await response.json();

        updateRouteMetricsDOM(data.routePerformanceSummary);
        updateRouteHealthDOM(data.routeHealthScore);
        updateRouteChartsData(data.headwayData, data.tripDurationData);

        // Save stops globally and display first page
        allStopsData = data.stops;
        stopCurrentPage = 1;
        updateStopsTableDOM();

        // Update incidents list
        updateIncidentsDOM(data.incidentLog);
    } catch (e) {
        console.error('Error fetching routes data:', e);
    }
}

function selectRouteTab(routeId) {
    activeRouteTab = routeId;

    // Toggle active classes on tab buttons
    document.querySelectorAll('.flex.items-center.bg-slate-100 button').forEach(btn => {
        btn.className = 'px-3 py-1.5 rounded-md text-xs font-semibold transition-all text-slate-500 hover:text-slate-800';
    });

    const activeBtn = document.getElementById(`tab-route-${routeId}`);
    if (activeBtn) {
        activeBtn.className = 'px-3 py-1.5 rounded-md text-xs font-semibold transition-all bg-[#003F87] text-white shadow-sm';
    }

    // Update the visual pill badge above the stop adherence log
    const routePillColor = document.getElementById('route-pill-color');
    const routePillLabel = document.getElementById('route-pill-label');
    if (routePillColor && routePillLabel) {
        const colorPalette = ['#003F87', '#3B6D11', '#854F0B', '#6B21A8', '#0F6E56', '#DC2626'];
        const label = routeId === 'all' ? 'All Routes' : (activeBtn ? activeBtn.innerText : 'Route ' + routeId);
        const color = routeId === 'all' ? '#6b7280' : (colorPalette[(parseInt(routeId) - 1) % colorPalette.length] || '#6b7280');
        routePillColor.style.backgroundColor = color;
        routePillLabel.innerText = label;
    }

    fetchRoutesData();
}

function updateRouteMetricsDOM(summary) {
    const tripsRunEl = document.getElementById('metric-trips-run');
    const completedEl = document.getElementById('metric-trips-completed');
    const ongoingEl = document.getElementById('metric-trips-ongoing');
    const dispatchedEl = document.getElementById('metric-trips-dispatched');
    const cancelledEl = document.getElementById('metric-trips-cancelled');
    const durationEl = document.getElementById('metric-avg-trip-duration');

    if (tripsRunEl) tripsRunEl.innerText = summary.trips_run ?? 0;
    if (completedEl) completedEl.innerText = summary.completed_trips ?? summary.trips_completed ?? 0;
    if (ongoingEl) ongoingEl.innerText = summary.ongoing_trips ?? 0;
    if (dispatchedEl) dispatchedEl.innerText = summary.dispatched_trips ?? 0;
    if (cancelledEl) cancelledEl.innerText = summary.cancelled_trips ?? 0;
    if (durationEl) durationEl.innerText = summary.avg_trip_duration_label || 'No data';
}

function updateRouteHealthDOM(health) {
    const scoreEl = document.getElementById('health-overall-score');
    const labelEl = document.getElementById('health-score-label');
    const noteEl = document.getElementById('health-data-note');

    const hasScore = health && health.overall_score !== null && health.overall_score !== undefined;
    if (scoreEl && !hasScore) {
        scoreEl.innerText = 'No score';
        scoreEl.className = 'text-[24px] font-medium leading-none text-slate-400 mt-3';
        if (labelEl) {
            labelEl.innerText = health?.data_status_label || 'Insufficient evidence';
            labelEl.className = 'text-[12px] font-bold mt-2 uppercase tracking-wide text-slate-400';
        }
    } else if (scoreEl) {
        scoreEl.innerText = `${health.overall_score}%`;
        const scoreColor = health.overall_score >= 85 ? 'text-[#3B6D11]' : (health.overall_score >= 70 ? 'text-[#854F0B]' : 'text-[#A32D2D]');
        scoreEl.className = `text-[48px] font-medium leading-none mt-3 ${scoreColor}`;
        if (labelEl) {
            labelEl.innerText = health.score_label;
            labelEl.className = `text-[14px] font-bold mt-2 uppercase tracking-wide ${scoreColor}`;
        }
    }

    if (noteEl) {
        noteEl.innerText = hasScore
            ? 'Complete actual evidence. Each component has equal weight.'
            : `${health?.data_status_label || 'Insufficient evidence'}. ${health?.missing_evidence?.[0] || 'Actual operational records are required.'}`;
    }

    const updateComponent = (barId, scoreId, evidenceId, score, evidence) => {
        const bar = document.getElementById(barId);
        const scoreLabel = document.getElementById(scoreId);
        const evidenceLabel = document.getElementById(evidenceId);
        const available = score !== null && score !== undefined;

        if (bar) bar.style.width = `${available ? score : 0}%`;
        if (scoreLabel) {
            scoreLabel.innerText = available ? `${score}%` : 'No data';
            scoreLabel.className = `font-mono-custom ${available ? 'text-[#001F44]' : 'text-slate-400'}`;
        }
        if (evidenceLabel) evidenceLabel.innerText = evidence || 'No actual evidence';
    };

    updateComponent('progress-health-completion', 'health-completion-score', 'health-completion-evidence', health?.completion_score, health?.completion_evidence);
    updateComponent('progress-health-hw', 'health-headway-score', 'health-headway-evidence', health?.headway_score, health?.headway_evidence);
    updateComponent('progress-health-incidents', 'health-incident-score', 'health-incident-evidence', health?.incident_free_score, health?.incident_evidence);
}

function initRouteCharts(headway, durations) {
    const hwContainer = document.getElementById('headwayRegularityChart');
    if (hwContainer) {
        headwayChart = echarts.init(hwContainer);
        const headwayOption = {
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    const row = params.data.meta;
                    return [
                        `<strong>${row.route_name} ${row.direction_label}</strong>`,
                        `${row.origin_name} to ${row.destination_name}`,
                        `Average: ${row.average_headway_minutes} min`,
                        `Range: ${row.minimum_headway_minutes}-${row.maximum_headway_minutes} min`,
                        `Observed gaps: ${row.observed_intervals}`
                    ].join('<br>');
                }
            },
            grid: {
                left: '4%',
                right: '3%',
                top: '10%',
                bottom: '8%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: [],
                axisTick: { show: false },
                axisLine: { lineStyle: { color: '#cbd5e1' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b', interval: 0 }
            },
            yAxis: {
                type: 'value',
                min: 0,
                splitLine: { lineStyle: { color: '#f1f5f9' } },
                axisLabel: {
                    fontFamily: 'Plus Jakarta Sans',
                    color: '#64748b',
                    formatter: '{value} min'
                }
            },
            series: [{
                name: 'Average actual headway',
                type: 'bar',
                data: [],
                barMaxWidth: 44,
                itemStyle: { borderRadius: [4, 4, 0, 0] }
            }]
        };
        headwayChart.setOption(headwayOption);
    }

    const durationContainer = document.getElementById('tripDurationChart');
    if (durationContainer) {
        tripDurationChart = echarts.init(durationContainer);
        const durationOption = {
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    const row = params.data.meta;
                    return [
                        `<strong>${row.route_name} ${row.direction_label}</strong>`,
                        `${row.origin_name} to ${row.destination_name}`,
                        `Average: ${row.average_duration_minutes} min`,
                        `Range: ${row.minimum_duration_minutes}-${row.maximum_duration_minutes} min`,
                        `Valid durations: ${row.valid_duration_trips}`
                    ].join('<br>');
                }
            },
            grid: {
                left: '4%',
                right: '3%',
                top: '10%',
                bottom: '8%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: [],
                axisTick: { show: false },
                axisLine: { lineStyle: { color: '#cbd5e1' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b', interval: 0 }
            },
            yAxis: {
                type: 'value',
                min: 0,
                splitLine: { lineStyle: { color: '#f1f5f9' } },
                axisLabel: {
                    fontFamily: 'Plus Jakarta Sans',
                    color: '#64748b',
                    formatter: '{value} min'
                }
            },
            series: [{
                name: 'Average actual duration',
                type: 'bar',
                data: [],
                barMaxWidth: 44,
                itemStyle: { borderRadius: [4, 4, 0, 0] }
            }]
        };
        tripDurationChart.setOption(durationOption);
    }

    updateRouteChartsData(headway, durations);
}

function updateRouteChartsData(headway, durations) {
    const headwayEmpty = document.getElementById('routeHeadwayEmptyState');
    const headwayWrapper = document.getElementById('headwayRegularityChart');
    const durationEmpty = document.getElementById('routeDurationEmptyState');
    const durationWrapper = document.getElementById('tripDurationChart');

    if (headwayChart) {
        if (!headway || headway.length === 0) {
            if (headwayEmpty) headwayEmpty.classList.remove('hidden');
            if (headwayWrapper) headwayWrapper.classList.add('invisible');
        } else {
            if (headwayEmpty) headwayEmpty.classList.add('hidden');
            if (headwayWrapper) headwayWrapper.classList.remove('invisible');

            headwayChart.setOption({
                xAxis: { data: headway.map(row => row.display_label) },
                series: [{
                    data: headway.map(row => ({
                        value: row.average_headway_minutes,
                        meta: row,
                        itemStyle: { color: row.route_color || '#378ADD' }
                    }))
                }]
            });
        }
    }

    if (tripDurationChart) {
        if (!durations || durations.length === 0) {
            if (durationEmpty) durationEmpty.classList.remove('hidden');
            if (durationWrapper) durationWrapper.classList.add('invisible');
        } else {
            if (durationEmpty) durationEmpty.classList.add('hidden');
            if (durationWrapper) durationWrapper.classList.remove('invisible');

            tripDurationChart.setOption({
                xAxis: { data: durations.map(row => row.display_label) },
                series: [{
                    data: durations.map(row => ({
                        value: row.average_duration_minutes,
                        meta: row,
                        itemStyle: { color: row.route_color || '#639922' }
                    }))
                }]
            });
        }
    }
}

// Dynamic local paginating stops list
function updateStopsTableDOM() {
    const tbody = document.getElementById('stop-table-body');
    const emptyState = document.getElementById('stop-table-empty');
    const wrapper = document.getElementById('stop-table-wrapper');
    const pagination = document.getElementById('stop-pagination-controls');

    if (!tbody) return;

    tbody.innerHTML = '';

    if (allStopsData.length === 0) {
        if (wrapper) wrapper.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        if (pagination) pagination.classList.add('hidden');
        return;
    }

    if (wrapper) wrapper.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');
    if (pagination) pagination.classList.remove('hidden');

    // Slice for pagination
    const startIdx = (stopCurrentPage - 1) * stopPerPage;
    const paginated = allStopsData.slice(startIdx, startIdx + stopPerPage);

    paginated.forEach(row => {
        const isAttributed = row.is_attributed === true;
        const rowBg = isAttributed ? 'hover:bg-slate-50' : 'bg-amber-50/40 hover:bg-amber-50/70';
        const stopName = escapeMarkup(row.stop_name);
        const routeName = escapeMarkup(row.route_name);
        const direction = escapeMarkup(row.direction_label);
        const routeColor = safeRouteColor(row.route_color);

        const tr = document.createElement('tr');
        tr.className = `transition-colors ${rowBg}`;
        tr.setAttribute('data-stop_name', row.stop_name);
        tr.setAttribute('data-display_label', row.display_label);
        tr.setAttribute('data-sequence', row.sequence ?? '');
        tr.setAttribute('data-recorded_boarded', row.recorded_boarded);
        tr.setAttribute('data-recorded_alighted', row.recorded_alighted);
        tr.setAttribute('data-trips_recorded', row.trips_recorded);

        tr.innerHTML = `
            <td class="py-3 px-4">
                <span class="flex items-center gap-2">
                    <i class="ti ${isAttributed ? 'ti-map-pin' : 'ti-map-pin-question'} text-slate-400 text-[14px]"></i>
                    <span class="font-medium text-[#001F44]">${stopName}</span>
                </span>
            </td>
            <td class="py-3 px-4">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full inline-block shrink-0" style="background-color: ${routeColor}"></span>
                    <span class="text-slate-600 text-[12px] font-medium">${routeName}</span>
                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500">${direction}</span>
                </div>
            </td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-600">${escapeMarkup(row.sequence_label)}</td>
            <td class="py-3 px-4 text-center font-mono-custom font-semibold text-[#003F87]">${formatNumber(row.recorded_boarded)}</td>
            <td class="py-3 px-4 text-center font-mono-custom font-semibold text-[#854F0B]">${formatNumber(row.recorded_alighted)}</td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${formatNumber(row.trips_recorded)}</td>
        `;
        tbody.appendChild(tr);
    });

    renderStopPaginationControls();
}

function renderStopPaginationControls() {
    const container = document.getElementById('stop-pagination-controls');
    if (!container) return;

    const totalPages = Math.ceil(allStopsData.length / stopPerPage);
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const prevDisabled = stopCurrentPage === 1 ? 'disabled opacity-50 cursor-not-allowed' : '';
    const nextDisabled = stopCurrentPage === totalPages ? 'disabled opacity-50 cursor-not-allowed' : '';

    const div = document.createElement('div');
    div.className = 'flex items-center justify-between w-full text-xs font-semibold text-slate-600 py-3 border-t border-slate-100 mt-2';

    let pageNumbersMarkup = '';
    for (let i = 1; i <= totalPages; i++) {
        const activeClass = i === stopCurrentPage
            ? 'bg-[#003F87] text-white shadow-sm'
            : 'hover:bg-slate-100 text-slate-600';
        pageNumbersMarkup += `
            <button onclick="gotoStopPage(${i})" class="h-8 w-8 flex items-center justify-center rounded-lg transition-colors ${activeClass}">
                ${i}
            </button>
        `;
    }

    div.innerHTML = `
        <span>Showing ${(stopCurrentPage-1)*stopPerPage + 1} to ${Math.min(stopCurrentPage*stopPerPage, allStopsData.length)} of ${allStopsData.length} stops</span>
        <div class="flex items-center gap-1">
            <button onclick="gotoStopPage(${stopCurrentPage-1})" ${prevDisabled} class="h-8 px-3 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors flex items-center justify-center gap-1">
                <i class="ti ti-chevron-left"></i> Previous
            </button>
            ${pageNumbersMarkup}
            <button onclick="gotoStopPage(${stopCurrentPage+1})" ${nextDisabled} class="h-8 px-3 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors flex items-center justify-center gap-1">
                Next <i class="ti ti-chevron-right"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

window.gotoStopPage = function(page) {
    const totalPages = Math.ceil(allStopsData.length / stopPerPage);
    if (page < 1 || page > totalPages) return;
    stopCurrentPage = page;
    updateStopsTableDOM();
};

function updateIncidentsDOM(incidents) {
    const feed = document.getElementById('incidents-log-feed');
    const badge = document.getElementById('incidents-log-badge');

    if (!feed) return;

    if (badge) {
        badge.innerText = `${incidents.length} ${incidents.length === 1 ? 'incident' : 'incidents'}`;
        badge.className = `rounded-full px-2.5 py-0.5 text-[12px] font-semibold ${incidents.length > 0 ? 'bg-[#FCEBEB] text-[#A32D2D]' : 'bg-slate-100 text-slate-500'}`;
    }

    feed.innerHTML = '';
    if (incidents.length === 0) {
        feed.innerHTML = `
            <div class="flex flex-col items-center justify-center py-10 min-h-[120px] text-center">
                <i class="ti ti-circle-check text-[36px] text-[#0F6E56]"></i>
                <h3 class="text-[14px] font-medium text-[#001F44] mt-2">No incidents recorded</h3>
                <p class="text-[13px] text-slate-400 mt-1">No operational incidents were recorded for this period.</p>
            </div>
        `;
        return;
    }

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);

    incidents.forEach(incident => {
        const statusBadge = incident.status === 'active' || incident.status === 'reported'
            ? 'bg-[#FCEBEB] text-[#A32D2D]'
            : (incident.status === 'under_review' ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-slate-100 text-slate-600');

        const div = document.createElement('div');
        div.className = 'bg-white border-[0.5px] border-slate-200 border-l-[3px] border-l-[#378ADD] rounded-md p-3.5 shadow-sm flex flex-col gap-2';
        div.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="px-2 py-0.5 rounded bg-blue-50 text-[#003F87] text-[10px] font-bold uppercase">Operational incident</span>
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-[#001F44] text-[11px] font-semibold">${escapeHtml(incident.event_type)}</span>
                </div>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase ${statusBadge}">${escapeHtml(incident.status_label)}</span>
            </div>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs">
                <span class="font-mono-custom text-[#001F44] font-semibold">${escapeHtml(incident.bus_id)}</span>
                <span class="text-slate-400 font-medium">&#8226;</span>
                <span class="text-slate-500 font-semibold">${escapeHtml(incident.driver_name)}</span>
                <span class="text-slate-400 font-medium">&#8226;</span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: ${incident.route_color}"></span>
                    <span class="text-[#001F44] font-semibold">${escapeHtml(incident.route)}</span>
                </span>
            </div>

            <p class="text-slate-500 text-[13px] leading-relaxed">${escapeHtml(incident.description)}</p>

            <div class="flex items-center justify-between pt-2 border-t border-slate-50">
                <span class="text-slate-400 text-xs font-semibold">${escapeHtml(incident.recorded_at)}</span>
                <a href="/admin/live-map?highlight=${encodeURIComponent(incident.bus_id)}"
                    class="flex items-center gap-1 text-[#003F87] text-xs font-bold hover:underline">
                    <i class="ti ti-map-pin text-[14px]"></i>
                    <span>View on map</span>
                </a>
            </div>
        `;
        feed.appendChild(div);
    });
}

// Client-side Stop Sorting
function sortStopTable(columnName) {
    if (allStopsData.length === 0) return;

    if (currentStopSortCol === columnName) {
        currentStopSortDir = currentStopSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentStopSortCol = columnName;
        currentStopSortDir = 'asc';
    }

    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'ti ti-arrows-sort text-slate-300 ml-1 sort-icon';
    });

    const activeIcon = document.getElementById(`sort-icon-${columnName}`);
    if (activeIcon) {
        activeIcon.className = currentStopSortDir === 'asc'
            ? 'ti ti-arrow-narrow-up text-slate-700 ml-1 sort-icon'
            : 'ti ti-arrow-narrow-down text-slate-700 ml-1 sort-icon';
    }

    allStopsData.sort((a, b) => {
        let valA = a[columnName];
        let valB = b[columnName];

        if (!isNaN(valA) && !isNaN(valB) && valA !== '' && valB !== '') {
            return currentStopSortDir === 'asc' ? Number(valA) - Number(valB) : Number(valB) - Number(valA);
        }
        return currentStopSortDir === 'asc'
            ? String(valA).localeCompare(String(valB), undefined, { numeric: true, sensitivity: 'base' })
            : String(valB).localeCompare(String(valA), undefined, { numeric: true, sensitivity: 'base' });
    });

    stopCurrentPage = 1;
    updateStopsTableDOM();
}

// CSV Export redirect with active filters
function exportRouteReport() {
    const startDate = document.getElementById('route-start-date')?.value || '';
    const endDate = document.getElementById('route-end-date')?.value || '';

    const queryParams = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        route_id: activeRouteTab
    });

    window.location.href = `${window.FleetPerformanceConfig.routeExportUrl}?${queryParams.toString()}`;
}

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// 3. INITIALIZATION & ROUTING OBSERVER
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

function setupSortingObservers() {
    // We bind sort listeners directly in table header elements onclick,
    // which simplifies MVC layout hooks.
}

const fleetPerformanceInitialized = { drivers: false, routes: false };

function initFleetPerformanceModule(screenName = null) {
    const shouldInitDrivers = (!screenName || screenName === 'drivers') && !fleetPerformanceInitialized.drivers && document.getElementById('metric-total-drivers');
    const shouldInitRoutes = (!screenName || screenName === 'routes') && !fleetPerformanceInitialized.routes && document.getElementById('metric-trips-completed');

    if (shouldInitDrivers) {
        fleetPerformanceInitialized.drivers = true;
        if (window.GoPasigDriversInitialData) {
            initDriverScoreChart(window.GoPasigDriversInitialData);
        }

        ['driver-start-date', 'driver-end-date', 'driver-route-id', 'driver-status'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', fetchDriversData);
        });

        let searchDebounceTimeout = null;
        document.getElementById('driver-search-input')?.addEventListener('input', () => {
            clearTimeout(searchDebounceTimeout);
            searchDebounceTimeout = setTimeout(fetchDriversData, 300);
        });

        document.getElementById('btn-export-drivers-csv')?.addEventListener('click', exportDriverReport);
    }

    if (shouldInitRoutes) {
        fleetPerformanceInitialized.routes = true;
        if (window.GoPasigRoutesInitialData) {
            allStopsData = window.GoPasigRoutesInitialData.stops;
            initRouteCharts(window.GoPasigRoutesInitialData.headway, window.GoPasigRoutesInitialData.duration);
            renderStopPaginationControls();
        }

        ['route-start-date', 'route-end-date'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', fetchRoutesData);
        });

        document.getElementById('btn-export-routes-csv')?.addEventListener('click', exportRouteReport);
    }
}

window.initFleetPerformanceModule = initFleetPerformanceModule;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initFleetPerformanceModule(), { once: true });
} else {
    initFleetPerformanceModule();
}

// Single-page dashboard tab navigation visibility listener
window.addEventListener('screen-shown', event => {
    const detail = event.detail[0] || event.detail;
    if (detail && detail.screen === 'drivers') {
        setTimeout(() => {
            if (driverScoreChart) driverScoreChart.resize();
            else if (window.GoPasigDriversInitialData) initDriverScoreChart(window.GoPasigDriversInitialData);
        }, 100);
    } else if (detail && detail.screen === 'routes') {
        setTimeout(() => {
            if (headwayChart) headwayChart.resize();
            if (tripDurationChart) tripDurationChart.resize();

            if (!headwayChart && window.GoPasigRoutesInitialData) {
                initRouteCharts(window.GoPasigRoutesInitialData.headway, window.GoPasigRoutesInitialData.duration);
            }
        }, 100);
    }
});

window.addEventListener('resize', () => {
    if (driverScoreChart) driverScoreChart.resize();
    if (headwayChart) headwayChart.resize();
    if (tripDurationChart) tripDurationChart.resize();
});
