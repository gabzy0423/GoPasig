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
    if (typeof updateAnalyticsKPIs === 'function') {
        updateAnalyticsKPIs();
    }

    // 2. Initialize Chart 1: Hourly grouped bar chart (2A)
    let hourlyLabels = ["5 AM", "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM", "12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM", "6 PM", "7 PM", "8 PM", "9 PM", "10 PM"];
    let datasets = [];

    // Extract dynamic route names
    let routeNames = [];
    if (typeof routeComparisonData !== 'undefined' && routeComparisonData && routeComparisonData.length > 0) {
        routeNames = routeComparisonData.map(r => r.route);
    } else if (typeof hourlyRidershipData !== 'undefined' && hourlyRidershipData && hourlyRidershipData.length > 0) {
        routeNames = Object.keys(hourlyRidershipData[0]).filter(k => k !== 'hour');
    }

    if (typeof hourlyRidershipData !== 'undefined' && hourlyRidershipData && hourlyRidershipData.length > 0) {
        hourlyLabels = hourlyRidershipData.map(d => d.hour);
        datasets = routeNames.map((name, idx) => {
            const rObj = routeComparisonData.find(r => r.route === name);
            const color = rObj?.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4];
            return {
                label: name,
                data: hourlyRidershipData.map(d => d[name] || 0),
                backgroundColor: color,
                borderRadius: 2
            };
        });
    } else {
        // Fallback mock static datasets
        datasets = [
            { label: 'Route A', data: [28, 67, 142, 118, 78, 52, 44, 61, 55, 48, 64, 89, 134, 128, 87, 52, 31, 14], backgroundColor: '#003F87', borderRadius: 2 },
            { label: 'Route B', data: [18, 45, 108, 95, 62, 38, 35, 48, 42, 37, 51, 71, 102, 97, 65, 39, 22, 9], backgroundColor: '#639922', borderRadius: 2 },
            { label: 'Route C', data: [14, 31, 72, 64, 41, 27, 22, 33, 29, 25, 36, 48, 68, 64, 44, 26, 16, 7], backgroundColor: '#BA7517', borderRadius: 2 }
        ];
    }

    const ctxHourly = document.getElementById('hourly-ridership-chart').getContext('2d');
    if (charts['hourly']) charts['hourly'].destroy();
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
                legend: { display: false },
                annotation: {
                    annotations: {
                        amPeak: {
                            type: 'box',
                            xMin: 1.5,
                            xMax: 2.5,
                            yMin: 0,
                            backgroundColor: 'rgba(0, 63, 135, 0.06)',
                            borderWidth: 0,
                            label: {
                                display: true,
                                content: 'AM Peak · 322 pax',
                                position: 'start',
                                yAlign: 'top',
                                font: { size: 10, weight: 'bold', family: 'Plus Jakarta Sans' },
                                color: '#003F87',
                                backgroundColor: 'rgba(230, 241, 251, 0.95)',
                                padding: 4
                            }
                        },
                        pmPeak: {
                            type: 'box',
                            xMin: 11.5,
                            xMax: 12.5,
                            yMin: 0,
                            backgroundColor: 'rgba(0, 63, 135, 0.06)',
                            borderWidth: 0,
                            label: {
                                display: true,
                                content: 'PM Peak · 298 pax',
                                position: 'start',
                                yAlign: 'top',
                                font: { size: 10, weight: 'bold', family: 'Plus Jakarta Sans' },
                                color: '#003F87',
                                backgroundColor: 'rgba(230, 241, 251, 0.95)',
                                padding: 4
                            }
                        }
                    }
                }
            }
        }
    });

    // 3. Initialize Chart 2: Route Doughnut (2B)
    let doughnutLabels = ['Route A', 'Route B', 'Route C'];
    let doughnutColors = ['#003F87', '#639922', '#BA7517'];
    let doughnutData = [532, 421, 331];

    if (typeof routeComparisonData !== 'undefined' && routeComparisonData && routeComparisonData.length > 0) {
        doughnutLabels = routeComparisonData.map(r => r.route);
        doughnutColors = routeComparisonData.map((r, idx) => r.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4]);
        doughnutData = routeComparisonData.map(r => r.pax);
    }

    const ctxDoughnut = document.getElementById('route-doughnut-chart').getContext('2d');
    if (charts['doughnut']) charts['doughnut'].destroy();
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

    // 4. Initialize Chart 3: Stop Boarding Horizontal Bars (2D)
    let stopLabels = ["Pasig City Hall", "Kapitolyo", "Ortigas Center", "Rosario", "Shaw Blvd", "Lores Ave", "Manggahan", "Market Ave", "Bagong Ilog", "Maybunga"];
    let stopValues = [218, 187, 165, 143, 121, 98, 87, 74, 61, 48];
    if (typeof stopBoardingData !== 'undefined' && stopBoardingData && stopBoardingData.length > 0) {
        stopLabels = stopBoardingData.slice(0, 10).map(s => s.name);
        stopValues = stopBoardingData.slice(0, 10).map(s => s.boarding);
    }

    const stopColors = stopValues.map((val, index) => {
        const colors = [
            '#003F87', '#003F87', '#185FA5', '#185FA5', '#378ADD',
            '#378ADD', '#85B7EB', '#85B7EB', '#B5D4F4', '#B5D4F4'
        ];
        return colors[index] || '#B5D4F4';
    });

    const ctxStops = document.getElementById('stop-boarding-chart').getContext('2d');
    if (charts['stops']) charts['stops'].destroy();
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
                    ctx.fillText(val + ' pax', bar.x + 8, bar.y);
                });
                ctx.restore();
            }
        }]
    });

    // 5. Initialize Chart 4: Pax Load over time lines (3C)
    const timelineLabels = ["5 AM", "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM", "12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM", "6 PM", "7 PM", "8 PM", "9 PM", "10 PM"];
    let timelineDatasets = [];

    const timelineConfig = [
        { color: '#003F87', style: 'circle', dash: [], radius: 4 },
        { color: '#639922', style: 'rect', dash: [6, 3], radius: 4.5 },
        { color: '#BA7517', style: 'triangle', dash: [2, 4], radius: 5 },
        { color: '#E24B4A', style: 'rectRot', dash: [10, 5], radius: 5 }
    ];

    // Helper function to extract and format 12-hour display hour (e.g. "5 AM", "12 PM", "1 PM") from depTime (e.g. "5:00 AM", "13:15" or "1:30 PM")
    function getHourLabelFromDepTime(depTimeStr) {
        if (!depTimeStr) return null;
        const match = depTimeStr.match(/(\d+):(\d+)\s*(AM|PM)?/i);
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
    const activePlates = [...new Set(tripData.map(t => t.plate))].slice(0, 4);

    if (activePlates.length > 0) {
        activePlates.forEach((plate, i) => {
            const config = timelineConfig[i] || timelineConfig[0];
            const dataPoints = timelineLabels.map(hourStr => {
                const trip = tripData.find(t => {
                    if (t.plate !== plate) return false;
                    const parsedHr = getHourLabelFromDepTime(t.depTime);
                    return parsedHr === hourStr;
                });
                if (trip) return trip.peakLoad;
                return null;
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
    } else {
        // Fallback mock static data
        timelineDatasets = [
            {
                label: 'PJY-8821',
                data: [15, 25, 45, 38, 28, 20, 18, 22, 19, 16, 22, 32, 45, 41, 30, 20, 12, 5],
                borderColor: '#003F87',
                backgroundColor: '#003F87',
                borderWidth: 2,
                pointStyle: 'circle',
                pointRadius: 4,
                borderDash: [],
                fill: false,
                tension: 0.15
            },
            {
                label: 'QRS-4412',
                data: [10, 18, 35, 30, 22, 15, 14, 25, 20, 15, 18, 26, 38, 35, 25, 15, 8, 3],
                borderColor: '#639922',
                backgroundColor: '#639922',
                borderWidth: 2,
                pointStyle: 'rect',
                pointRadius: 4.5,
                borderDash: [6, 3],
                fill: false,
                tension: 0.15
            },
            {
                label: 'TUV-3301',
                data: [5, 12, 28, 25, 18, 12, 10, 18, 15, 12, 15, 22, 30, 28, 18, 10, 5, 2],
                borderColor: '#BA7517',
                backgroundColor: '#BA7517',
                borderWidth: 2,
                pointStyle: 'triangle',
                pointRadius: 5,
                borderDash: [2, 4],
                fill: false,
                tension: 0.15
            },
            {
                label: 'MNO-2211',
                data: [8, 15, 45, 32, 24, 18, 15, 20, 17, 14, 20, 28, 42, 45, 32, 18, 10, 4],
                borderColor: '#E24B4A',
                backgroundColor: '#E24B4A',
                borderWidth: 2,
                pointStyle: 'rectRot',
                pointRadius: 5,
                borderDash: [10, 5],
                fill: false,
                tension: 0.15
            }
        ];
    }

    const ctxTimeline = document.getElementById('pax-load-timeline-chart').getContext('2d');
    if (charts['timeline']) charts['timeline'].destroy();
    const dynamicCapacity = typeof busCapacityLimit !== 'undefined' ? busCapacityLimit : 45;
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
                    max: 50,
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

    // 6. Initialize Chart 5: 30-Day Historical Trend (4C)
    let trendLabels = [];
    let trendDatasets = [];
    let todayPax = 1284;

    if (typeof kpisData !== 'undefined' && kpisData && kpisData.total_pax_today) {
        todayPax = parseInt(kpisData.total_pax_today.replace(/,/g, '')) || 1284;
    }

    if (typeof historicalTrendData !== 'undefined' && historicalTrendData && historicalTrendData.length > 0) {
        trendLabels = historicalTrendData.map(d => d.label);
        
        // Dynamic datasets builder
        trendDatasets.push({
            label: 'Total',
            data: historicalTrendData.map(d => d.total || 0),
            borderColor: '#003F87',
            backgroundColor: '#003F87',
            borderWidth: 2,
            pointRadius: function(context) {
                return context.dataIndex === (trendLabels.length - 1) ? 5 : 2;
            },
            fill: false,
            tension: 0.15
        });

        routeNames.forEach((name, idx) => {
            const rObj = routeComparisonData.find(r => r.route === name);
            const color = rObj?.color || ['#185FA5', '#639922', '#BA7517', '#E24B4A'][idx % 4];
            trendDatasets.push({
                label: name,
                data: historicalTrendData.map(d => d[name] || 0),
                borderColor: color,
                borderWidth: 1.5,
                borderDash: idx % 2 === 0 ? [6, 3] : [2, 4],
                pointRadius: 0,
                fill: false,
                tension: 0.15
            });
        });
    } else {
        // Fallback mock static trend data
        let trendA = [];
        let trendB = [];
        let trendC = [];
        let totalTrend = [];
        for (let i = 1; i <= 30; i++) {
            const date = new Date();
            date.setDate(date.getDate() - (30 - i));
            const dayOfWeek = date.getDay(); // 0 = Sunday
            const label = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            const wave = Math.round(Math.sin(dayOfWeek * 0.8) * 50);
            let base = dayOfWeek === 0 ? 350 : (dayOfWeek === 6 ? 420 : 550);
            const total = base + wave;

            const a = Math.round(total * 0.42);
            const b = Math.round(total * 0.33);
            const c = total - a - b;

            trendLabels.push(label);
            trendA.push(a);
            trendB.push(b);
            trendC.push(c);
            totalTrend.push(total);
        }

        trendDatasets = [
            {
                label: 'Total',
                data: totalTrend,
                borderColor: '#003F87',
                backgroundColor: '#003F87',
                borderWidth: 2,
                pointRadius: function(context) {
                    return context.dataIndex === (trendLabels.length - 1) ? 5 : 2;
                },
                fill: false,
                tension: 0.15
            },
            {
                label: 'Route A',
                data: trendA,
                borderColor: '#185FA5',
                borderWidth: 1.5,
                borderDash: [6, 3],
                pointRadius: 0,
                fill: false,
                tension: 0.15
            },
            {
                label: 'Route B',
                data: trendB,
                borderColor: '#639922',
                borderWidth: 1.5,
                borderDash: [6, 3],
                pointRadius: 0,
                fill: false,
                tension: 0.15
            },
            {
                label: 'Route C',
                data: trendC,
                borderColor: '#BA7517',
                borderWidth: 1.5,
                borderDash: [2, 4],
                pointRadius: 0,
                fill: false,
                tension: 0.15
            }
        ];
    }

    const ctxTrend = document.getElementById('historical-trend-chart').getContext('2d');
    if (charts['trend']) charts['trend'].destroy();
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
                    ticks: { font: { family: 'Plus Jakarta Sans', size: 9, weight: '600' } },
                    grid: { color: '#F1F5F9' }
                }
            },
            plugins: {
                legend: { display: false },
                annotation: {
                    annotations: {
                        todayLine: {
                            type: 'line',
                            yMin: todayPax,
                            yMax: todayPax,
                            borderColor: '#003F87',
                            borderWidth: 1.2,
                            borderDash: [4, 4],
                            label: {
                                display: true,
                                content: `Today: ${todayPax.toLocaleString()} pax`,
                                position: 'end',
                                font: { size: 8, weight: 'bold', family: 'Plus Jakarta Sans' },
                                color: '#003F87',
                                backgroundColor: 'rgba(230, 241, 251, 0.95)',
                                padding: 2
                            }
                        }
                    }
                }
            }
        }
    });

    // 7. Bind interactive click events once
    if (!analyticsEventsBound) {
        bindAnalyticsInteractiveEvents();
        analyticsEventsBound = true;
    }
}
