// Main initialization controller triggered on Analytics screen switch
function initAnalyticsDashboard() {
    // 1. Render all HTML template elements
    renderHeatmapGrid();
    renderTripTable();
    renderBusSummaryCards();
    renderForecastTable();
    if (typeof renderDriverPerformanceTable === 'function') {
        renderDriverPerformanceTable();
    }
    if (typeof renderRouteComparisonTable === 'function') {
        renderRouteComparisonTable();
    }
    if (typeof renderTopStopsTable === 'function') {
        renderTopStopsTable();
    }
    if (typeof switchPredictionRoute === 'function') {
        switchPredictionRoute('all');
    }
    if (typeof updateAnalyticsKPIs === 'function') {
        updateAnalyticsKPIs();
    }

    // 2. Initialize Chart 1: Hourly grouped bar chart (2A)
    if (charts['hourly']) {
        charts['hourly'].destroy();
        delete charts['hourly'];
    }

    const hasHourlyData = (typeof hourlyRidershipData !== 'undefined' && hourlyRidershipData && hourlyRidershipData.length > 0);
    showChartEmptyState('hourly-ridership-chart', !hasHourlyData);

    if (hasHourlyData && document.getElementById('hourly-ridership-chart')) {
        let hourlyLabels = hourlyRidershipData.map(d => d.hour);
        let routeNames = [];
        if (typeof routeComparisonData !== 'undefined' && routeComparisonData && routeComparisonData.length > 0) {
            routeNames = routeComparisonData.map(r => r.route);
        } else {
            routeNames = Object.keys(hourlyRidershipData[0]).filter(k => k !== 'hour');
        }

        let datasets = routeNames.map((name, idx) => {
            const rObj = routeComparisonData.find(r => r.route === name);
            const color = rObj?.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4];
            return {
                label: name,
                data: hourlyRidershipData.map(d => d[name] || 0),
                backgroundColor: color,
                borderRadius: 2
            };
        });

        const ctxHourly = document.getElementById('hourly-ridership-chart').getContext('2d');
        charts['hourly'] = new Chart(ctxHourly, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { autoSkip: false, maxRotation: 45, font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { display: false }
                    },
                    y: {
                        min: 0,
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { color: '#F1F5F9' }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 3. Initialize Chart 2: Route Doughnut (2B)
    if (charts['doughnut']) {
        charts['doughnut'].destroy();
        delete charts['doughnut'];
    }

    const hasDoughnutData = (typeof routeComparisonData !== 'undefined' && routeComparisonData && routeComparisonData.length > 0);
    showChartEmptyState('route-doughnut-chart', !hasDoughnutData);

    if (hasDoughnutData && document.getElementById('route-doughnut-chart')) {
        let doughnutLabels = routeComparisonData.map(r => r.route);
        let doughnutColors = routeComparisonData.map((r, idx) => r.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4]);
        let doughnutData = routeComparisonData.map(r => r.tripsRun ?? r.trips ?? 0);

        const ctxDoughnut = document.getElementById('route-doughnut-chart').getContext('2d');
        charts['doughnut'] = new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: doughnutLabels,
                datasets: [{
                    data: doughnutData,
                    backgroundColor: doughnutColors,
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                animation: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 4. Initialize Chart 3: Stop Boarding Horizontal Bars (2D)
    if (charts['stops']) {
        charts['stops'].destroy();
        delete charts['stops'];
    }

    const hasStopsData = (typeof stopBoardingData !== 'undefined' && stopBoardingData && stopBoardingData.length > 0);
    showChartEmptyState('stop-boarding-chart', !hasStopsData);

    if (hasStopsData && document.getElementById('stop-boarding-chart')) {
        let stopLabels = stopBoardingData.slice(0, 10).map(s => s.name);
        let stopValues = stopBoardingData.slice(0, 10).map(s => s.boarding);

        const stopColors = stopValues.map((val, index) => {
            const colors = [
                '#003F87', '#003F87', '#185FA5', '#185FA5', '#378ADD',
                '#378ADD', '#85B7EB', '#85B7EB', '#B5D4F4', '#B5D4F4'
            ];
            return colors[index] || '#B5D4F4';
        });

        const ctxStops = document.getElementById('stop-boarding-chart').getContext('2d');
        charts['stops'] = new Chart(ctxStops, {
            type: 'bar',
            data: {
                labels: stopLabels,
                datasets: [{
                    data: stopValues,
                    backgroundColor: stopColors,
                    borderRadius: 4,
                    barThickness: 16
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        min: 0,
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { color: '#F1F5F9' }
                    },
                    y: {
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            },
            plugins: [{
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx, data } = chart;
                    ctx.save();
                    ctx.font = 'bold 11px Plus Jakarta Sans';
                    ctx.textBaseline = 'middle';
                    const meta = chart.getDatasetMeta(0);
                    meta.data.forEach((bar, index) => {
                        const val = data.datasets[0].data[index];
                        const color = data.datasets[0].backgroundColor[index];
                        ctx.fillStyle = color;
                        ctx.fillText(val + ' requests', bar.x + 8, bar.y);
                    });
                    ctx.restore();
                }
            }]
        });
    }

    // 5. Initialize Chart 4: Pax Load over time lines (3C)
    if (charts['timeline']) {
        charts['timeline'].destroy();
        delete charts['timeline'];
    }

    const hasTimelineData = (typeof peakLoadTimelineData !== 'undefined' && peakLoadTimelineData && peakLoadTimelineData.length > 0);
    showChartEmptyState('pax-load-timeline-chart', !hasTimelineData);

    if (hasTimelineData && document.getElementById('pax-load-timeline-chart')) {
        const timelineTrips = peakLoadTimelineData;
        const timelineLabels = ["5 AM", "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM", "12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM", "6 PM", "7 PM", "8 PM", "9 PM", "10 PM"];
        let timelineDatasets = [];

        const timelineConfig = [
            { color: '#003F87', style: 'circle', dash: [], radius: 4 },
            { color: '#639922', style: 'rect', dash: [6, 3], radius: 4.5 },
            { color: '#BA7517', style: 'triangle', dash: [2, 4], radius: 5 },
            { color: '#E24B4A', style: 'rectRot', dash: [10, 5], radius: 5 }
        ];

        // Extract a 12-hour display label from the API's formatted startedAt value.
        function getHourLabelFromTime(timeString) {
            if (!timeString) return null;
            const match = timeString.match(/(\d+):(\d+)\s*(AM|PM)?/i);
            if (!match) return null;
            let hour = parseInt(match[1]);
            const ampm = match[3] ? match[3].toUpperCase() : null;

            if (ampm) {
                // It's 12-hour format with AM/PM
                return `${hour} ${ampm}`;
            } else {
                // It's 24-hour format
                const ampm24 = hour >= 12 ? 'PM' : 'AM';
                let hr12 = hour % 12;
                if (hr12 === 0) hr12 = 12;
                return `${hr12} ${ampm24}`;
            }
        }

        // Group by bus plate
        const activePlates = [...new Set(timelineTrips.map(t => t.plate))].slice(0, 4);

        activePlates.forEach((plate, i) => {
            const config = timelineConfig[i] || timelineConfig[0];
            const dataPoints = timelineLabels.map(hourStr => {
                const matchingTrips = timelineTrips.filter(t => {
                    if (t.plate !== plate) return false;
                    const parsedHr = getHourLabelFromTime(t.startedAt);
                    return parsedHr === hourStr;
                });

                if (matchingTrips.length === 0) return null;

                return Math.max(...matchingTrips.map(trip => Number(trip.peakLoad) || 0));
            });

            timelineDatasets.push({
                label: plate,
                data: dataPoints,
                borderColor: config.color,
                backgroundColor: config.color,
                borderWidth: 2,
                pointStyle: config.style,
                pointRadius: config.radius,
                borderDash: config.dash,
                fill: false,
                tension: 0.15
            });
        });

        const ctxTimeline = document.getElementById('pax-load-timeline-chart').getContext('2d');
        const dynamicCapacity = typeof busCapacityLimit !== 'undefined' ? busCapacityLimit : 45;
        const peakLoads = timelineTrips
            .map(trip => Number(trip.peakLoad))
            .filter(load => Number.isFinite(load));
        const maxPeakLoad = peakLoads.length > 0 ? Math.max(...peakLoads) : 0;
        const chartMax = Math.ceil(Math.max(50, dynamicCapacity, maxPeakLoad) / 10) * 10;
        charts['timeline'] = new Chart(ctxTimeline, {
            type: 'line',
            data: {
                labels: timelineLabels,
                datasets: timelineDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { display: false }
                    },
                    y: {
                        min: 0,
                        max: chartMax,
                        ticks: { stepSize: 10, font: { family: 'Plus Jakarta Sans', size: 10, weight: '600' } },
                        grid: { color: '#F1F5F9' }
                    }
                },
                plugins: {
                    legend: { display: false },
                    annotation: {
                        annotations: {
                            maxCap: {
                                type: 'line',
                                yMin: dynamicCapacity,
                                yMax: dynamicCapacity,
                                borderColor: '#E24B4A',
                                borderWidth: 1.5,
                                borderDash: [4, 4],
                                label: {
                                    display: true,
                                    content: `Max capacity (${dynamicCapacity})`,
                                    position: 'end',
                                    font: { size: 9, weight: 'bold', family: 'Plus Jakarta Sans' },
                                    color: '#E24B4A',
                                    backgroundColor: 'rgba(252, 235, 235, 0.95)',
                                    padding: 3
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    // 6. Initialize Chart 5: finalized, direction-aware 30-day demand
    if (charts['trend']) {
        charts['trend'].destroy();
        delete charts['trend'];
    }

    const historicalPayload = (typeof historicalDemandData !== 'undefined' && historicalDemandData)
        ? historicalDemandData
        : null;
    const historicalDates = historicalPayload && Array.isArray(historicalPayload.dates)
        ? historicalPayload.dates
        : [];
    const historicalSeries = historicalPayload && Array.isArray(historicalPayload.series)
        ? historicalPayload.series
        : [];
    const hasTrendData = historicalSeries.some(series =>
        Array.isArray(series.points) && series.points.some(point => point.value !== null)
    );
    const trendRange = document.getElementById('historical-demand-range');
    const trendCoverage = document.getElementById('historical-demand-coverage');
    const trendLegend = document.getElementById('historical-demand-legend');

    if (trendRange && historicalPayload && historicalPayload.range) {
        trendRange.textContent = `${historicalPayload.range.start} to ${historicalPayload.range.end} | ${historicalPayload.range.timezone}`;
    }
    if (trendCoverage) {
        trendCoverage.textContent = historicalPayload && historicalPayload.coverage
            ? `${historicalPayload.basis}. ${historicalPayload.coverage.label}.`
            : 'Finalized actual commuter check-ins only.';
    }
    if (trendLegend) {
        trendLegend.replaceChildren();
        historicalSeries.forEach(series => {
            const item = document.createElement('span');
            item.className = 'inline-flex items-center gap-1';

            const swatch = document.createElement('span');
            swatch.className = 'inline-block w-4 border-t-2';
            swatch.style.borderTopColor = series.color;
            swatch.style.borderTopStyle = series.direction === 'inbound' ? 'dashed' : 'solid';

            const label = document.createElement('span');
            label.textContent = series.label;

            item.append(swatch, label);
            trendLegend.appendChild(item);
        });
    }

    showChartEmptyState('historical-trend-chart', !hasTrendData);

    if (hasTrendData && document.getElementById('historical-trend-chart')) {
        const trendLabels = historicalDates.map(date => date.label);
        const trendDatasets = historicalSeries.map(series => ({
                label: series.label,
                data: series.points.map(point => point.value),
                borderColor: series.color,
                backgroundColor: series.color,
                borderWidth: 1.5,
                borderDash: series.direction === 'inbound' ? [6, 3] : [],
                pointRadius: context => series.points[context.dataIndex]?.coverage === 'partial' ? 4 : 2,
                pointHoverRadius: 5,
                fill: false,
                spanGaps: false,
                tension: 0.15
            }));

        const ctxTrend = document.getElementById('historical-trend-chart').getContext('2d');
        charts['trend'] = new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: trendDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: {
                            font: { family: 'Plus Jakarta Sans', size: 9, weight: '600' },
                            callback: function(val, index) {
                                return index % 5 === 0 || index === (trendLabels.length - 1) ? this.getLabelForValue(val) : '';
                            }
                        },
                        grid: { display: false }
                    },
                    y: {
                        min: 0,
                        beginAtZero: true,
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 9, weight: '600' } },
                        grid: { color: '#F1F5F9' },
                        title: {
                            display: true,
                            text: 'Commuter check-ins',
                            font: { family: 'Plus Jakarta Sans', size: 9, weight: '700' },
                            color: '#64748B'
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const series = historicalSeries[context.datasetIndex];
                                const point = series.points[context.dataIndex];
                                const coverage = point.coverage === 'partial' ? ' (partial coverage)' : '';

                                return `${series.label}: ${point.value} check-ins${coverage}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 7. Bind interactive click events once
    if (!analyticsEventsBound) {
        bindAnalyticsInteractiveEvents();
        analyticsEventsBound = true;
    }
}
/**
 * Overlay empty states dynamically on chart containers when database data is missing.
 */
function showChartEmptyState(canvasId, isEmpty) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const wrapper = (canvasId === 'route-doughnut-chart')
        ? canvas.parentElement.parentElement
        : canvas.parentElement;

    if (!wrapper) return;

    let overlay = wrapper.querySelector('.chart-empty-state-overlay');

    if (isEmpty) {
        if (canvasId === 'route-doughnut-chart') {
            const centerText = wrapper.querySelector('.absolute.flex.flex-col');
            if (centerText) centerText.style.display = 'none';
            canvas.parentElement.style.display = 'none';
        } else {
            canvas.style.display = 'none';
        }

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'chart-empty-state-overlay absolute inset-0 flex flex-col items-center justify-center bg-slate-50/50 rounded-xl border border-dashed border-slate-200 text-center p-4 z-10';
            overlay.innerHTML = `
                <div class="h-10 w-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-2">
                    <i class="ti ti-chart-bar text-xl"></i>
                </div>
                <p class="text-xs font-bold text-slate-700">No Data Available</p>
                <p class="text-[10px] text-slate-400 font-semibold mt-0.5">There is no historical data recorded for this selection yet.</p>
            `;
            wrapper.appendChild(overlay);
        } else {
            overlay.classList.remove('hidden');
        }
    } else {
        if (canvasId === 'route-doughnut-chart') {
            const centerText = wrapper.querySelector('.absolute.flex.flex-col');
            if (centerText) centerText.style.display = 'flex';
            canvas.parentElement.style.display = 'block';
        } else {
            canvas.style.display = 'block';
        }

        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
}
