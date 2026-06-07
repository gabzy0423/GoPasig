/**
 * GoPasig Fleet Ops - Analytics & Reports Javascript Controller
 * Handles ECharts initialization, date filtering, data-driven recommendation refreshing, exports, and AJAX calls.
 */

let routeSummaryChart, hourlyRidershipChart;
let currentSortCol = '';
let currentSortDir = 'asc';

function initAnalyticsCharts() {
    // Destroy existing instances before reinit
    if (routeSummaryChart) { routeSummaryChart.dispose(); routeSummaryChart = null; }
    if (hourlyRidershipChart) { hourlyRidershipChart.dispose(); hourlyRidershipChart = null; }

    const rsContainer = document.getElementById('routePassengersChart');
    if (rsContainer) {
        routeSummaryChart = echarts.init(rsContainer);
        const routeOption = {
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                formatter: '{b}: {c} passengers'
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
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' },
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
                name: 'Passengers',
                type: 'bar',
                data: [],
                barWidth: '60%',
                itemStyle: {
                    borderRadius: [0, 4, 4, 0]
                }
            }]
        };
        routeSummaryChart.setOption(routeOption);
    }

    const hrContainer = document.getElementById('hourlyRidershipChart');
    if (hrContainer) {
        hourlyRidershipChart = echarts.init(hrContainer);
        const hourlyOption = {
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
                data: [],
                axisTick: { show: false },
                axisLine: { lineStyle: { color: '#cbd5e1' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
            },
            yAxis: {
                type: 'value',
                splitLine: { lineStyle: { color: '#f1f5f9' } },
                axisLabel: { fontFamily: 'Plus Jakarta Sans', color: '#64748b' }
            },
            series: []
        };
        hourlyRidershipChart.setOption(hourlyOption);
    }

    // Set initial data if available
    if (window.GoPasigAnalyticsInitialData) {
        updateAnalyticsChartsData(
            window.GoPasigAnalyticsInitialData.routeSummary,
            window.GoPasigAnalyticsInitialData.hourlyRidership
        );
    }
}

function updateAnalyticsChartsData(routeSummary, hourlyRidership) {
    if (routeSummaryChart && routeSummary) {
        const sortedData = [...routeSummary].reverse();
        const names = sortedData.map(d => d.route_name.split(' — ')[0]);
        const values = sortedData.map(d => ({
            value: d.total_passengers,
            itemStyle: { color: d.color || '#378ADD' }
        }));

        const emptyState = document.getElementById('routePassengersEmptyState');
        if (emptyState) {
            if (routeSummary.length === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        routeSummaryChart.setOption({
            yAxis: { data: names },
            series: [{ data: values }]
        });
    }

    if (hourlyRidershipChart && hourlyRidership) {
        const routesData = {};
        let totalPassengers = 0;

        hourlyRidership.forEach(item => {
            const routeName = item.route.split(' — ')[0];
            if (!routesData[routeName]) {
                routesData[routeName] = {
                    name: routeName,
                    color: item.color,
                    dataMap: {}
                };
            }
            routesData[routeName].dataMap[item.hour] = item.count;
            totalPassengers += item.count;
        });

        const uniqueHours = [];
        hourlyRidership.forEach(item => {
            if (!uniqueHours.includes(item.hour)) {
                uniqueHours.push(item.hour);
            }
        });

        const emptyState = document.getElementById('hourlyRidershipEmptyState');
        if (emptyState) {
            if (totalPassengers === 0) {
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
            }
        }

        const series = Object.values(routesData).map(route => {
            const data = uniqueHours.map(h => route.dataMap[h] || 0);
            return {
                name: route.name,
                type: 'line',
                data: data,
                smooth: true,
                lineStyle: { width: 2.5, color: route.color },
                itemStyle: { color: route.color }
            };
        });

        hourlyRidershipChart.setOption({
            xAxis: { data: uniqueHours },
            legend: {
                data: Object.keys(routesData)
            },
            series: series
        }, { notMerge: true });
    }
}

// Client-side vanilla sorting function
function sortTable(columnName) {
    const tbody = document.getElementById('bus-table-body');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));

    if (currentSortCol === columnName) {
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortCol = columnName;
        currentSortDir = 'asc';
    }

    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'ti ti-arrows-sort text-slate-300 ml-1 sort-icon';
    });

    const activeIcon = document.getElementById(`sort-icon-${columnName}`);
    if (activeIcon) {
        activeIcon.className = currentSortDir === 'asc'
            ? 'ti ti-arrow-narrow-up text-slate-700 ml-1 sort-icon'
            : 'ti ti-arrow-narrow-down text-slate-700 ml-1 sort-icon';
    }

    rows.sort((a, b) => {
        let valA = a.getAttribute(`data-${columnName}`);
        let valB = b.getAttribute(`data-${columnName}`);

        if (!isNaN(valA) && !isNaN(valB) && valA !== '' && valB !== '') {
            return currentSortDir === 'asc' ? Number(valA) - Number(valB) : Number(valB) - Number(valA);
        }
        return currentSortDir === 'asc'
            ? valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' })
            : valB.localeCompare(valA, undefined, { numeric: true, sensitivity: 'base' });
    });

    if (window.tableObserver) window.tableObserver.disconnect();
    rows.forEach(row => tbody.appendChild(row));
    if (window.tableObserver) {
        window.tableObserver.observe(document.getElementById('bus-table-body'), { childList: true });
    }
}

function reapplySort() {
    if (currentSortCol) {
        const col = currentSortCol;
        const dir = currentSortDir;
        currentSortCol = '';
        currentSortDir = '';
        if (dir === 'desc') {
            sortTable(col);
            sortTable(col);
        } else {
            sortTable(col);
        }
    }
}

function setupTableSortingObserver() {
    const target = document.getElementById('bus-table-body');
    if (!target) return;
    window.tableObserver = new MutationObserver(() => {
        if (window.tableObserver) window.tableObserver.disconnect();
        reapplySort();
        if (window.tableObserver) window.tableObserver.observe(target, { childList: true });
    });
    window.tableObserver.observe(target, { childList: true });
}

// Fetch and render analytics data
async function fetchAnalyticsData() {
    const startDate = document.getElementById('analytics-start-date')?.value;
    const endDate = document.getElementById('analytics-end-date')?.value;
    const routeId = document.getElementById('analytics-route-id')?.value;
    const reportType = document.getElementById('analytics-report-type')?.value;

    if (!startDate || !endDate || !routeId) return;

    try {
        const url = `/fleet/api/analytics-data?start_date=${startDate}&end_date=${endDate}&route_id=${routeId}&report_type=${reportType}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();

        // 1. Update KPI counts
        document.getElementById('metric-total-passengers').innerText = data.metricSummary.total_passengers;
        document.getElementById('metric-trips-completed').innerText = data.metricSummary.trips_completed;
        document.getElementById('metric-avg-per-trip').innerText = data.metricSummary.avg_per_trip;
        document.getElementById('metric-utilization-rate').innerText = data.metricSummary.utilization_rate;
        document.getElementById('metric-busiest-route').innerText = data.metricSummary.busiest_route;
        document.getElementById('metric-busiest-route-count').innerText = `(${data.metricSummary.busiest_route_count} pax)`;
        document.getElementById('metric-peak-hour').innerText = data.metricSummary.peak_hour;

        // 2. Re-plot Charts
        updateAnalyticsChartsData(data.routeSummary, data.hourlyRidership);

        // 3. Populate Bus Logs Table
        const tbody = document.getElementById('bus-table-body');
        const logCount = document.getElementById('bus-log-count');
        const tableWrapper = document.getElementById('bus-table-wrapper');
        const tableEmpty = document.getElementById('bus-table-empty');

        if (tbody) {
            tbody.innerHTML = '';
            logCount.innerText = `${data.busLogs.length} buses`;

            if (data.busLogs.length === 0) {
                tableWrapper.classList.add('hidden');
                tableEmpty.classList.remove('hidden');
            } else {
                tableWrapper.classList.remove('hidden');
                tableEmpty.classList.add('hidden');

                data.busLogs.forEach(row => {
                    let utilBg = 'bg-[#EAF3DE] text-[#3B6D11]';
                    let utilLabel = 'Normal';
                    if (row.utilization_rate >= 90) {
                        utilBg = 'bg-[#FCEBEB] text-[#A32D2D]';
                        utilLabel = 'High load';
                    } else if (row.utilization_rate >= 70) {
                        utilBg = 'bg-[#FAEEDA] text-[#854F0B]';
                        utilLabel = 'Moderate';
                    }

                    let statusBg = 'bg-[#FCEBEB] text-[#A32D2D]';
                    if (row.status === 'Active') {
                        statusBg = 'bg-[#E1F5EE] text-[#0F6E56]';
                    } else if (row.status === 'Idle') {
                        statusBg = 'bg-[#F1EFE8] text-[#5F5E5A]';
                    } else if (row.status === 'Delayed') {
                        statusBg = 'bg-[#FAEEDA] text-[#854F0B]';
                    }

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50 transition-colors';
                    tr.setAttribute('data-bus_id', row.bus_id);
                    tr.setAttribute('data-assigned_route', row.assigned_route);
                    tr.setAttribute('data-trips_completed', row.trips_completed);
                    tr.setAttribute('data-total_passengers', row.total_passengers);
                    tr.setAttribute('data-capacity', row.capacity);
                    tr.setAttribute('data-utilization_rate', row.utilization_rate);
                    tr.setAttribute('data-status', row.status);

                    tr.innerHTML = `
                        <td class="py-3 px-4 font-mono-custom text-[#001F44] font-semibold">${row.bus_id}</td>
                        <td class="py-3 px-4">
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: ${row.route_color}"></span>
                                <span class="font-medium text-[#001F44]">${row.assigned_route}</span>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${row.trips_completed}</td>
                        <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${Number(row.total_passengers).toLocaleString()}</td>
                        <td class="py-3 px-4 text-center font-mono-custom text-slate-700">${row.capacity}</td>
                        <td class="py-3 px-4 text-center">
                            <div class="inline-flex flex-col items-center gap-1.5 w-full">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide {{ $utilBg }}">${utilBg.includes('A32D2D') ? 'High load' : (utilBg.includes('854F0B') ? 'Moderate' : 'Normal')} (${row.utilization_rate}%)</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide ${statusBg}">${row.status}</span>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        // 4. Update Recommendations panel
        const recContainer = document.getElementById('recommendations-container');
        const recEmpty = document.getElementById('recommendations-empty');
        const recUpdatedTime = document.getElementById('recommendations-last-updated');

        if (recContainer) {
            recContainer.innerHTML = '';
            recUpdatedTime.innerText = `Last updated: ${data.lastUpdatedTime}`;

            if (data.dispatchRecommendations.length === 0) {
                recContainer.classList.add('hidden');
                recEmpty.classList.remove('hidden');
            } else {
                recContainer.classList.remove('hidden');
                recEmpty.classList.add('hidden');

                data.dispatchRecommendations.forEach(rec => {
                    let badgeStyle = 'bg-[#FAEEDA] text-[#854F0B]';
                    if (rec.status === 'Underserved') {
                        badgeStyle = 'bg-[#FCEBEB] text-[#A32D2D]';
                    } else if (rec.status === 'Adequate') {
                        badgeStyle = 'bg-[#EAF3DE] text-[#3B6D11]';
                    }

                    const div = document.createElement('div');
                    div.className = 'bg-white border-[0.5px] border-slate-200 rounded-md p-4 shadow-sm flex flex-col justify-between';
                    div.innerHTML = `
                        <div>
                            <div class="flex items-center justify-between mb-3 border-b border-slate-50 pb-2">
                                <span class="text-[14px] font-semibold text-[#001F44]">${rec.route}</span>
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider ${badgeStyle}">${rec.status}</span>
                            </div>

                            <div class="space-y-2 mt-2">
                                <div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Recommended Dispatch</span>
                                    <span class="text-[20px] font-semibold text-[#003F87]">${rec.recommended_dispatch}</span>
                                </div>
                                <div>
                                    <span class="text-[11px] text-slate-500 font-medium block">Peak window: <strong>${rec.peak_window}</strong></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100">
                            <p class="text-[12px] text-slate-500 italic leading-relaxed">"${rec.insight_blurb}"</p>
                        </div>
                    `;
                    recContainer.appendChild(div);
                });
            }
        }
    } catch (error) {
        console.error('Failed to fetch analytics data on change:', error);
    }
}

// Show alert message utility
function showAnalyticsAlert(message) {
    const alertBox = document.getElementById('analytics-alert');
    const alertMsg = document.getElementById('analytics-alert-message');
    if (alertBox && alertMsg) {
        alertMsg.innerText = message;
        alertBox.classList.remove('hidden');
    }
}

// Document ready and events registration
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if we are on the Analytics screen page or container is visible
    if (document.getElementById('routePassengersChart')) {
        initAnalyticsCharts();
        setupTableSortingObserver();

        // 1. Date filters and route selector change listeners
        const filters = ['analytics-start-date', 'analytics-end-date', 'analytics-route-id', 'analytics-report-type'];
        filters.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', fetchAnalyticsData);
            }
        });

        // 2. Download CSV Action
        const btnCsv = document.getElementById('btn-export-csv');
        if (btnCsv) {
            btnCsv.addEventListener('click', () => {
                const startDate = document.getElementById('analytics-start-date').value;
                const endDate = document.getElementById('analytics-end-date').value;
                const routeId = document.getElementById('analytics-route-id').value;
                window.location.href = `/fleet/api/analytics-export?start_date=${startDate}&end_date=${endDate}&route_id=${routeId}`;
            });
        }

        // 3. Download PDF Action
        const btnPdf = document.getElementById('btn-export-pdf');
        if (btnPdf) {
            btnPdf.addEventListener('click', () => {
                showAnalyticsAlert("PDF export triggered — use your browser's Print dialog (Ctrl+P) to save as PDF.");
                setTimeout(() => window.print(), 300);
            });
        }

        // 4. Refresh Recommendations Action
        const btnRefresh = document.getElementById('btn-refresh-recommendations');
        if (btnRefresh) {
            btnRefresh.addEventListener('click', async () => {
                const btnIcon = btnRefresh.querySelector('i');
                if (btnIcon) btnIcon.classList.add('animate-spin');

                await fetchAnalyticsData();

                if (btnIcon) btnIcon.classList.remove('animate-spin');
                showAnalyticsAlert("Recommendations updated based on latest ridership data!");
            });
        }

        // Window resize
        window.addEventListener('resize', () => {
            if (routeSummaryChart) routeSummaryChart.resize();
            if (hourlyRidershipChart) hourlyRidershipChart.resize();
        });
    }
});
