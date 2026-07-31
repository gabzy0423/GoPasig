/**
 * GoPasig Fleet Ops - Schedule Compliance JS Controller
 * Handles ECharts regularity trends, local pagination, AJAX filters, and list sorting.
 */

window.ScheduleComplianceConfig = {
    dataUrl: '/fleet/api/schedule-compliance-data',
    exportUrl: '/fleet/api/schedule-compliance-export',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Chart References
let onTimeRouteChart = null;
let delayHourTrendChart = null;

// Table & Pagination State
let currentComplianceSortCol = '';
let currentComplianceSortDir = 'asc';

let allTripsData = [];
let tripCurrentPage = 1;
const tripPerPage = 10;

// Shared Helper: Format Numbers
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

async function fetchComplianceData() {
    const dateFrom = document.getElementById('compliance-date-from')?.value || '';
    const dateTo = document.getElementById('compliance-date-to')?.value || '';
    const routeId = document.getElementById('compliance-route-id')?.value || 'all';
    const driver = document.getElementById('compliance-driver-id')?.value || 'all';
    const status = document.getElementById('compliance-status-id')?.value || 'all';

    try {
        const queryParams = new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo,
            route_id: routeId,
            driver: driver,
            status: status
        });

        const response = await fetch(`${window.ScheduleComplianceConfig.dataUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch schedule compliance data');
        const data = await response.json();

        updateComplianceMetricsDOM(data.complianceSummary);
        updateComplianceChartsData(data.routeCompliance, data.delayTrend);
        
        // Save trips globally for local pagination
        allTripsData = data.tripLogs;
        tripCurrentPage = 1;
        updateTripsTableDOM();

        // Update delayed routes & late drivers
        updateDelayedRoutesDOM(data.delayedRoutes);
        updateLateDriversDOM(data.lateDrivers);
    } catch (e) {
        console.error('Error fetching compliance data:', e);
    }
}

function updateComplianceMetricsDOM(summary) {
    const rateEl = document.getElementById('metric-on-time-rate');
    const completedEl = document.getElementById('metric-trips-completed');
    const onTimeEl = document.getElementById('metric-on-time-count');
    const lateEl = document.getElementById('metric-late-count');
    const missedEl = document.getElementById('metric-missed-count');

    if (rateEl) {
        rateEl.innerText = `${summary.on_time_rate}%`;
        const color = summary.on_time_rate >= 80 ? 'text-[#0F6E56]' : (summary.on_time_rate >= 60 ? 'text-[#854F0B]' : 'text-[#A32D2D]');
        rateEl.className = `text-[24px] font-medium leading-none mt-2 ${color}`;
    }
    if (completedEl) completedEl.innerText = summary.trips_completed;
    if (onTimeEl) onTimeEl.innerText = summary.on_time_count;
    if (lateEl) lateEl.innerText = summary.late_count;
    if (missedEl) missedEl.innerText = summary.missed_count;
}

function initComplianceCharts(routeCompliance, delayTrend) {
    if (!routeCompliance && window.GoPasigScheduleComplianceInitialData) {
        routeCompliance = window.GoPasigScheduleComplianceInitialData.routeCompliance;
    }
    if (!delayTrend && window.GoPasigScheduleComplianceInitialData) {
        delayTrend = window.GoPasigScheduleComplianceInitialData.delayTrend;
    }

    const rcContainer = document.getElementById('onTimeRatePerRouteChart');
    if (rcContainer) {
        onTimeRouteChart = echarts.init(rcContainer);
        const routeOption = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: '{b}: {c}%'
            },
            grid: {
                left: '3%',
                right: '8%',
                top: '5%',
                bottom: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'value',
                min: 0,
                max: 100,
                axisLabel: { formatter: '{value}%', fontFamily: 'Plus Jakarta Sans', color: '#64748b' },
                splitLine: { lineStyle: { color: '#f1f5f9' } }
            },
            yAxis: {
                type: 'category',
                data: [],
                axisTick: { show: false },
                axisLine: { show: false },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b', fontSize: 11 }
            },
            series: [{
                name: 'On-time Rate',
                type: 'bar',
                data: [],
                barWidth: '60%',
                itemStyle: {
                    borderRadius: [0, 4, 4, 0]
                }
            }]
        };
        onTimeRouteChart.setOption(routeOption);
    }

    const dtContainer = document.getElementById('delayTrendChart');
    if (dtContainer) {
        delayHourTrendChart = echarts.init(dtContainer);
        const delayOption = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'line' }
            },
            legend: {
                show: true,
                top: '0%',
                textStyle: { fontFamily: 'Plus Jakarta Sans', color: '#64748b', fontSize: 11 },
                itemWidth: 12,
                itemHeight: 8
            },
            grid: {
                left: '3%',
                right: '4%',
                top: '15%',
                bottom: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: ['05:00', '07:00', '09:00', '11:00', '13:00', '15:00', '17:00'],
                axisTick: { show: false },
                axisLine: { lineStyle: { color: '#cbd5e1' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
            },
            yAxis: {
                type: 'value',
                minInterval: 1,
                splitLine: { lineStyle: { color: '#f1f5f9' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
            },
            series: []
        };
        delayHourTrendChart.setOption(delayOption);
    }

    updateComplianceChartsData(routeCompliance, delayTrend);
}

function updateComplianceChartsData(routeCompliance, delayTrend) {
    if (onTimeRouteChart && routeCompliance && routeCompliance.length > 0) {
        const sortedData = [...routeCompliance].reverse();
        const names = sortedData.map(d => d.route_name);
        const rates = sortedData.map(d => ({
            value: d.on_time_rate,
            itemStyle: { color: d.color || '#378ADD' }
        }));
        onTimeRouteChart.setOption({
            yAxis: { data: names },
            series: [{ data: rates }]
        });
    }

    if (delayHourTrendChart && delayTrend) {
        const hours = [...new Set(delayTrend.map(d => d.label))].sort((a, b) => a.localeCompare(b));
        const routesData = {};
        let totalDelays = 0;

        delayTrend.forEach(item => {
            const routeName = item.route;
            if (!routesData[routeName]) {
                routesData[routeName] = {
                    name: routeName,
                    color: item.color,
                    data: new Array(hours.length).fill(0)
                };
            }
            const hourIdx = hours.indexOf(item.label);
            if (hourIdx !== -1) {
                routesData[routeName].data[hourIdx] = item.delayed_count;
                totalDelays += item.delayed_count;
            }
        });

        const emptyState = document.getElementById('delayTrendEmptyState');
        const canvas = document.getElementById('delayTrendChart');
        if (emptyState && canvas) {
            if (totalDelays === 0) {
                emptyState.classList.remove('hidden');
                canvas.classList.add('invisible');
            } else {
                emptyState.classList.add('hidden');
                canvas.classList.remove('invisible');
            }
        }

        const series = Object.values(routesData).map(route => {
            return {
                name: route.name,
                type: 'line',
                data: route.data,
                smooth: true,
                lineStyle: { width: 2.5, color: route.color },
                itemStyle: { color: route.color }
            };
        });

        delayHourTrendChart.setOption({
            xAxis: {
                data: hours
            },
            legend: {
                data: Object.keys(routesData)
            },
            series: series
        }, { notMerge: true });
    }
}

function updateTripsTableDOM() {
    const tbody = document.getElementById('compliance-table-body');
    const emptyState = document.getElementById('compliance-table-empty');
    const wrapper = document.getElementById('compliance-table-wrapper');
    const pagination = document.getElementById('compliance-pagination-controls');

    if (!tbody) return;

    tbody.innerHTML = '';

    const recordsBadge = document.getElementById('compliance-records-badge');
    if (recordsBadge) recordsBadge.innerText = `${allTripsData.length} trips`;

    if (allTripsData.length === 0) {
        if (wrapper) wrapper.classList.add('hidden');
        if (emptyState) emptyState.classList.remove('hidden');
        if (pagination) pagination.classList.add('hidden');
        return;
    }

    if (wrapper) wrapper.classList.remove('hidden');
    if (emptyState) emptyState.classList.add('hidden');
    if (pagination) pagination.classList.remove('hidden');

    const startIdx = (tripCurrentPage - 1) * tripPerPage;
    const paginated = allTripsData.slice(startIdx, startIdx + tripPerPage);

    paginated.forEach(row => {
        const badgeClasses = {
            'On Time': { bg: 'bg-[#E1F5EE] text-[#0F6E56]', icon: 'ti-check' },
            'Late': { bg: 'bg-[#FAEEDA] text-[#854F0B]', icon: 'ti-clock-exclamation' },
            'Early': { bg: 'bg-[#E6F1FB] text-[#185FA5]', icon: 'ti-clock-bolt' },
            'Missed': { bg: 'bg-[#FCEBEB] text-[#A32D2D]', icon: 'ti-x' }
        };
        const statusBadge = badgeClasses[row.status] || { bg: 'bg-slate-100 text-slate-600', icon: 'ti-help' };

        let varText = '--';
        let varColor = 'text-slate-400';
        if (row.status !== 'Missed') {
            const minutes = row.variance_minutes;
            if (minutes >= -2 && minutes <= 2) {
                varText = 'On time';
                varColor = 'text-[#0F6E56] font-semibold';
            } else if (minutes > 2) {
                varText = `+${minutes} min`;
                varColor = 'text-[#A32D2D] font-bold';
            } else {
                varText = `âˆ’${Math.abs(minutes)} min`;
                varColor = 'text-[#0F6E56] font-semibold';
            }
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.setAttribute('data-trip_id', row.trip_id);
        tr.setAttribute('data-bus_id', row.bus_id);
        tr.setAttribute('data-driver_name', row.driver_name);
        tr.setAttribute('data-route_name', row.route_name);
        tr.setAttribute('data-scheduled_departure', row.scheduled_departure);
        tr.setAttribute('data-actual_departure', row.actual_departure);
        tr.setAttribute('data-variance_minutes', row.variance_minutes);
        tr.setAttribute('data-status', row.status);

        tr.innerHTML = `
            <td class="py-3 px-4 font-mono-custom text-[#001F44] font-medium">${row.trip_id}</td>
            <td class="py-3 px-4 font-mono-custom text-slate-600">${row.bus_id}</td>
            <td class="py-3 px-4 text-slate-700">
                <span class="flex items-center gap-1">
                    <span class="font-medium">${row.driver_name}</span>
                    <i class="ti ti-id text-slate-400 text-[12px]"></i>
                </span>
            </td>
            <td class="py-3 px-4">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: ${row.route_color}"></span>
                    <span class="font-medium text-[#001F44] text-[12px] bg-slate-50 border border-slate-100 rounded-full px-2 py-0.5">${row.route_name}</span>
                </span>
            </td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-600">${row.scheduled_departure}</td>
            <td class="py-3 px-4 text-center font-mono-custom text-slate-600">${row.actual_departure}</td>
            <td class="py-3 px-4 text-center font-mono-custom ${varColor}">${varText}</td>
            <td class="py-3 px-4">
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold flex items-center gap-1 w-max ${statusBadge.bg}">
                    <i class="ti ${statusBadge.icon}"></i>
                    <span>${row.status}</span>
                </span>
            </td>
            <td class="py-3 px-4 text-center">
                <a href="/admin/live-map?trip=${row.schedule_id}"
                    class="inline-block px-2.5 py-1 rounded border border-black/10 text-[11px] font-bold text-[#003F87] hover:bg-slate-50 transition-colors">
                    View trip
                </a>
            </td>
        `;
        tbody.appendChild(tr);
    });

    renderCompliancePaginationControls();
    reapplyComplianceTableSort();
}

function renderCompliancePaginationControls() {
    const container = document.getElementById('compliance-pagination-controls');
    if (!container) return;

    const totalPages = Math.ceil(allTripsData.length / tripPerPage);
    container.innerHTML = '';

    if (totalPages <= 1) {
        // Just show count summary
        container.innerHTML = `
            <div class="flex items-center justify-between w-full text-xs font-semibold text-slate-500 py-3 border-t border-slate-100 mt-2">
                <span>Showing 1 to ${allTripsData.length} of ${allTripsData.length} trips</span>
            </div>
        `;
        return;
    }

    const prevDisabled = tripCurrentPage === 1 ? 'disabled opacity-50 cursor-not-allowed' : '';
    const nextDisabled = tripCurrentPage === totalPages ? 'disabled opacity-50 cursor-not-allowed' : '';

    const div = document.createElement('div');
    div.className = 'flex flex-col sm:flex-row items-center justify-between w-full text-xs font-semibold text-slate-500 py-3 border-t border-slate-100 mt-2 gap-4';
    
    let pageNumbersMarkup = '';
    // Limit to max 5 page numbers around current page for readability
    let startPage = Math.max(1, tripCurrentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === tripCurrentPage 
            ? 'bg-[#003F87] text-white shadow-sm' 
            : 'hover:bg-slate-100 text-slate-600';
        pageNumbersMarkup += `
            <button onclick="gotoCompliancePage(${i})" class="h-8 w-8 flex items-center justify-center rounded-lg transition-colors ${activeClass}">
                ${i}
            </button>
        `;
    }

    div.innerHTML = `
        <span>Showing ${(tripCurrentPage-1)*tripPerPage + 1} to ${Math.min(tripCurrentPage*tripPerPage, allTripsData.length)} of ${allTripsData.length} trips</span>
        <div class="flex items-center gap-1">
            <button onclick="gotoCompliancePage(${tripCurrentPage-1})" ${prevDisabled} class="h-8 px-3 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors flex items-center justify-center gap-1">
                <i class="ti ti-chevron-left"></i> Previous
            </button>
            ${pageNumbersMarkup}
            <button onclick="gotoCompliancePage(${tripCurrentPage+1})" ${nextDisabled} class="h-8 px-3 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors flex items-center justify-center gap-1">
                Next <i class="ti ti-chevron-right"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
}

window.gotoCompliancePage = function(page) {
    const totalPages = Math.ceil(allTripsData.length / tripPerPage);
    if (page < 1 || page > totalPages) return;
    tripCurrentPage = page;
    updateTripsTableDOM();
};

function updateDelayedRoutesDOM(delayedRoutes) {
    const list = document.getElementById('delayed-routes-list');
    if (!list) return;

    list.innerHTML = '';
    if (delayedRoutes.length === 0) {
        list.innerHTML = `
            <div class="flex items-center gap-2 text-[#0F6E56] py-3">
                <i class="ti ti-circle-check text-[20px]"></i>
                <p class="text-[13px] font-medium">All routes are running on time.</p>
            </div>
        `;
        return;
    }

    delayedRoutes.forEach(dr => {
        const pct = Math.min(100, (dr.total_delay_minutes / 20) * 100);
        const div = document.createElement('div');
        div.className = 'flex items-center gap-3 h-[16px]';
        div.innerHTML = `
            <div class="flex items-center gap-2 w-[120px] shrink-0">
                <span class="w-2 h-2 rounded-full inline-block shrink-0" style="background-color: ${dr.route_color}"></span>
                <span class="text-[13px] font-semibold text-[#001F44] truncate">${dr.route_name}</span>
            </div>
            <div class="flex-1 bg-[#F1EFE8] h-[4px] rounded-[2px] overflow-hidden">
                <div class="bg-[#E24B4A] h-full rounded-[2px]" style="width: ${pct}%"></div>
            </div>
            <span class="text-[12px] text-slate-500 font-medium w-[60px] text-right shrink-0 font-mono-custom">${dr.total_delay_minutes} min</span>
        `;
        list.appendChild(div);
    });
}

function updateLateDriversDOM(lateDrivers) {
    const list = document.getElementById('late-drivers-list');
    if (!list) return;

    list.innerHTML = '';
    if (lateDrivers.length === 0) {
        list.innerHTML = `
            <div class="flex items-center gap-2 text-[#0F6E56] py-3">
                <i class="ti ti-circle-check text-[20px]"></i>
                <p class="text-[13px] font-medium">No drivers with late departures.</p>
            </div>
        `;
        return;
    }

    lateDrivers.forEach(ld => {
        const div = document.createElement('div');
        div.className = 'bg-white border-[0.5px] border-slate-200 rounded-md p-3 shadow-sm flex flex-col gap-2';
        div.innerHTML = `
            <div class="flex justify-between items-center">
                <span class="text-[13px] font-semibold text-[#001F44]">${ld.driver_name}</span>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#FAEEDA] text-[#854F0B] font-mono-custom">
                    ${ld.late_count} late ${ld.late_count === 1 ? 'trip' : 'trips'}
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: ${ld.route_color}"></span>
                    <span class="text-[11px] font-bold text-[#001F44] bg-slate-50 border border-slate-100 rounded px-1.5 py-0.5">${ld.assigned_route}</span>
                </span>
                <span class="text-[11px] text-slate-400 font-semibold font-mono-custom">Avg delay: +${ld.avg_delay_minutes} min</span>
            </div>
        `;
        list.appendChild(div);
    });
}

// Client-side Compliance Sorting
function sortComplianceTable(columnName) {
    const tbody = document.getElementById('compliance-table-body');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;

    if (currentComplianceSortCol === columnName) {
        currentComplianceSortDir = currentComplianceSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentComplianceSortCol = columnName;
        currentComplianceSortDir = 'asc';
    }

    document.querySelectorAll('.sort-icon').forEach(i => {
        i.className = 'ti ti-arrows-sort text-slate-300 ml-1 sort-icon';
    });

    const activeIcon = document.getElementById(`sort-icon-${columnName}`);
    if (activeIcon) {
        activeIcon.className = currentComplianceSortDir === 'asc'
            ? 'ti ti-arrow-narrow-up text-slate-700 ml-1 sort-icon'
            : 'ti ti-arrow-narrow-down text-slate-700 ml-1 sort-icon';
    }

    allTripsData.sort((a, b) => {
        let valA = a[columnName];
        let valB = b[columnName];

        if (!isNaN(valA) && !isNaN(valB) && valA !== '' && valB !== '') {
            return currentComplianceSortDir === 'asc' ? Number(valA) - Number(valB) : Number(valB) - Number(valA);
        }
        return currentComplianceSortDir === 'asc'
            ? String(valA).localeCompare(String(valB), undefined, { numeric: true, sensitivity: 'base' })
            : String(valB).localeCompare(String(valA), undefined, { numeric: true, sensitivity: 'base' });
    });

    tripCurrentPage = 1;
    updateTripsTableDOM();
}

function reapplyComplianceTableSort() {
    if (currentComplianceSortCol) {
        const col = currentComplianceSortCol;
        const dir = currentComplianceSortDir;
        currentComplianceSortCol = '';
        currentComplianceSortDir = '';
        if (dir === 'desc') {
            sortComplianceTable(col);
            sortComplianceTable(col);
        } else {
            sortComplianceTable(col);
        }
    }
}

// CSV Export compliance report
function exportComplianceReport() {
    const dateFrom = document.getElementById('compliance-date-from')?.value || '';
    const dateTo = document.getElementById('compliance-date-to')?.value || '';
    const routeId = document.getElementById('compliance-route-id')?.value || 'all';
    const driver = document.getElementById('compliance-driver-id')?.value || 'all';
    const status = document.getElementById('compliance-status-id')?.value || 'all';

    const queryParams = new URLSearchParams({
        date_from: dateFrom,
        date_to: dateTo,
        route_id: routeId,
        driver: driver,
        status: status
    });

    window.location.href = `${window.ScheduleComplianceConfig.exportUrl}?${queryParams.toString()}`;
}

// Document load hook
let fleetScheduleModuleInitialized = false;

function initFleetScheduleModule() {
    if (fleetScheduleModuleInitialized || !document.getElementById('onTimeRatePerRouteChart')) return;
    fleetScheduleModuleInitialized = true;


        // Load initial data state
        if (window.GoPasigScheduleComplianceInitialData) {
            allTripsData = window.GoPasigScheduleComplianceInitialData.tripLogs;
            initComplianceCharts(
                window.GoPasigScheduleComplianceInitialData.routeCompliance,
                window.GoPasigScheduleComplianceInitialData.delayTrend
            );
            renderCompliancePaginationControls();
        }

        // Apply filters button trigger
        document.getElementById('btn-apply-compliance-filters')?.addEventListener('click', fetchComplianceData);
}

window.initFleetScheduleModule = initFleetScheduleModule;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFleetScheduleModule, { once: true });
} else {
    initFleetScheduleModule();
}

// Single-page dashboard tab navigation visibility listener
window.addEventListener('screen-shown', event => {
    const detail = event.detail[0] || event.detail;
    if (detail && detail.screen === 'schedule') {
        setTimeout(() => {
            if (onTimeRouteChart) onTimeRouteChart.resize();
            if (delayHourTrendChart) delayHourTrendChart.resize();
            
            if (!onTimeRouteChart && window.GoPasigScheduleComplianceInitialData) {
                initComplianceCharts(
                    window.GoPasigScheduleComplianceInitialData.routeCompliance,
                    window.GoPasigScheduleComplianceInitialData.delayTrend
                );
            }
        }, 100);
    }
});

window.addEventListener('resize', () => {
    if (onTimeRouteChart) onTimeRouteChart.resize();
    if (delayHourTrendChart) delayHourTrendChart.resize();
});
