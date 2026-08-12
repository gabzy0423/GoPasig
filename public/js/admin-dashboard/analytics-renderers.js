    // Helper: Dynamic Color codes mapping for weekly matrix matrix
    function getHeatmapColor(pax) {
        if (pax <= 50) return '#F0F5FF';
        if (pax <= 100) return '#B5D4F4';
        if (pax <= 175) return '#378ADD';
        if (pax <= 250) return '#185FA5';
        return '#003F87';
    }

    // Helper: Highly aligned hourly statistics based on daily dispatcher rules
    function getHeatmapPaxValue(dayIndex, hourIndex) {
        if (typeof heatmapData !== 'undefined' && heatmapData) {
            const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const dayName = daysOfWeek[dayIndex];
            if (heatmapData[dayName] && heatmapData[dayName][hourIndex] !== undefined && heatmapData[dayName][hourIndex] !== null) {
                return heatmapData[dayName][hourIndex];
            }
        }
        return null;
    }

    // 2C: Heatmap matrix grids renderer
    function renderHeatmapGrid() {
        const grid = document.getElementById('heatmap-matrix-grid');
        if (!grid) return;
        grid.innerHTML = '';

        const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
        const fullDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
        const hours = ["5 AM", "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM", "12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM", "6 PM", "7 PM", "8 PM", "9 PM", "10 PM"];

        for (let dayIdx = 0; dayIdx < 7; dayIdx++) {
            const rowDiv = document.createElement('div');
            rowDiv.className = "flex items-center gap-1.5";
            
            // Day Label child matching headers pl padding
            rowDiv.innerHTML = `<div class="w-[60px] text-[12px] font-bold text-slate-500 shrink-0 text-right pr-2 select-none">${days[dayIdx]}</div>`;
            
            // Render 18 hours cells
            for (let hourIdx = 0; hourIdx < 18; hourIdx++) {
                const pax = getHeatmapPaxValue(dayIdx, hourIdx);
                let color;
                let tooltipText;
                if (pax === null) {
                    color = '#e2e8f0'; // slate-200 (grey empty state)
                    tooltipText = `${fullDays[dayIdx]} · ${hours[hourIdx]} · No data available`;
                } else {
                    color = getHeatmapColor(pax);
                    tooltipText = `${fullDays[dayIdx]} · ${hours[hourIdx]} · ${pax} pax across all routes`;
                }
                
                rowDiv.innerHTML += `
                    <div class="w-8 h-8 rounded cursor-pointer transition-all hover:scale-105" 
                         style="background-color: ${color};" 
                         title="${tooltipText}"></div>
                `;
            }
            grid.appendChild(rowDiv);
        }
    }

    // Helper: HTML badge renderer for trip table
    function getRouteBadgeHtml(route) {
        let color = '#888780';
        if (typeof routeComparisonData !== 'undefined' && routeComparisonData) {
            const found = routeComparisonData.find(r => r.route === route);
            if (found && found.color) {
                color = found.color;
            }
        }
        if (color === '#888780' && typeof routeColors !== 'undefined' && routeColors) {
            color = routeColors[route] || routeColors[route.replace('Route ', '')] || '#888780';
        }
        return `<span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border" style="background-color: ${color}15; color: ${color}; border-color: ${color}25;">${route}</span>`;
    }

    // Helper: Capacity load indicators
    function getCapacityCellHtml(capacityPct) {
        let textColor = '';
        let chipHtml = '';
        let dotColor = '';
        let iconHtml = '';
        let boldClass = '';

        if (capacityPct < 60) {
            textColor = 'text-slate-500';
            dotColor = 'bg-slate-400';
        } else if (capacityPct < 80) {
            textColor = 'text-[#185FA5]';
            dotColor = 'bg-[#185FA5]';
        } else if (capacityPct < 90) {
            textColor = 'text-[#854F0B]';
            dotColor = 'bg-[#BA7517]';
            chipHtml = `<span class="inline-flex rounded-full bg-[#FEF7ED] px-2 py-0.5 text-[9px] font-bold text-[#BA7517] ml-1 uppercase leading-none">High</span>`;
        } else if (capacityPct < 100) {
            textColor = 'text-[#A32D2D]';
            dotColor = 'bg-[#E24B4A]';
            chipHtml = `<span class="inline-flex rounded-full bg-[#FDF2F2] px-2 py-0.5 text-[9px] font-bold text-[#E24B4A] ml-1 uppercase leading-none">Near full</span>`;
        } else {
            textColor = 'text-[#E24B4A]';
            dotColor = 'bg-[#E24B4A]';
            boldClass = 'font-black';
            chipHtml = `<span class="inline-flex rounded-full bg-[#FCEBEB] px-2 py-0.5 text-[9px] font-bold text-[#E24B4A] ml-1 uppercase border border-[#E24B4A]/10 leading-none">Full</span>`;
            iconHtml = `<i class="ti ti-alert-circle text-xs text-[#E24B4A] mr-1 animate-pulse shrink-0"></i>`;
        }

        return `
            <div class="flex items-center gap-1.5 ${textColor} ${boldClass} shrink-0">
                <span class="h-2 w-2 rounded-full ${dotColor} shrink-0"></span>
                ${iconHtml}
                <span>${capacityPct}%</span>
                ${chipHtml}
            </div>
        `;
    }

    // 3A: trip load records table population
    function renderTripTable() {
        const tbody = document.getElementById('trip-pax-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!tripData || tripData.length === 0) {
            const emptyTr = document.createElement('tr');
            emptyTr.className = "text-center text-slate-400";
            emptyTr.innerHTML = `
                <td class="px-4 py-8 font-bold" colspan="10">No trip load records for the selected period.</td>
            `;
            tbody.appendChild(emptyTr);
            return;
        }

        tripData.forEach(trip => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.setAttribute('data-trip-route', trip.route);

            tr.innerHTML = `
                <td class="px-4 py-2.5 font-bold">${trip.tripNo}</td>
                <td class="px-4 py-2.5 font-bold text-slate-800">${trip.driver}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600">${trip.plate}</td>
                <td class="px-4 py-2.5">${getRouteBadgeHtml(trip.route)}</td>
                <td class="px-4 py-2.5">${getStatusChipHtml(trip.status)}</td>
                <td class="px-4 py-2.5 text-slate-500">${trip.startedAt}</td>
                <td class="px-4 py-2.5 text-slate-500">${trip.endedAt}</td>
                <td class="px-4 py-2.5 font-bold text-slate-700">${trip.recordedBoarded ?? 0}</td>
                <td class="px-4 py-2.5 font-bold text-slate-700">${trip.recordedAlighted ?? 0}</td>
                <td class="px-4 py-2.5 font-bold text-slate-700">${trip.peakLoad}</td>
            `;
            tbody.appendChild(tr);
        });

    }

    // Helper: bus summary widget builder (3B)
    function getBusSummaryCardHtml(bus) {
        const capacity = bus.capacity || 45;
        const statusChip = getStatusChipHtml(bus.status);
        const peakLoadClass = bus.peakLoad >= capacity ? 'text-[#E24B4A] font-extrabold' : 'text-slate-700 font-semibold';
        
        // utilization color warning
        const isHighUtil = bus.avgCapacity > 85;
        const barColor = isHighUtil ? 'bg-[#E24B4A]' : 'bg-[#003F87]';
        const warningTooltip = isHighUtil ? 'title="High utilization warning (>85% avg capacity)"' : '';
        
        return `
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col justify-between hover:border-[#003F87]/20 transition duration-200 h-[195px] select-none">
                <div class="flex items-center justify-between border-b border-slate-50 pb-2 shrink-0">
                    <span class="font-mono text-xs font-bold text-[#003F87]">${bus.plate}</span>
                    ${statusChip}
                </div>
                
                <div class="space-y-1.5 mt-2 flex-1 text-[11px] font-semibold text-slate-500">
                    <div class="flex justify-between">
                        <span>Trips run:</span>
                        <span class="text-slate-800 font-bold">${bus.trips}</span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span>Passengers handled:</span>
                        <span class="text-[#003F87] text-lg font-black leading-none">${bus.totalPax}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Avg peak load / trip:</span>
                        <span class="text-slate-800 font-bold">${bus.avgPax}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Peak load:</span>
                        <span class="${peakLoadClass}">${bus.peakLoad} / ${capacity}</span>
                    </div>
                </div>
                
                <!-- Utilization Bar -->
                <div class="mt-3 shrink-0 space-y-1">
                    <div class="flex justify-between text-[9px] font-bold text-slate-400 uppercase">
                        <span>Utilization:</span>
                        <span>${bus.avgCapacity}% avg</span>
                    </div>
                    <div class="h-1.5 w-full bg-[#E6F1FB] rounded-full overflow-hidden" ${warningTooltip}>
                        <div class="${barColor} h-full rounded-full transition-all duration-500" style="width: ${bus.avgCapacity}%;"></div>
                    </div>
                </div>
                
                <div class="border-t border-slate-50 mt-3 pt-2 flex items-center gap-1 text-[10px] font-bold text-slate-400 shrink-0">
                    <i class="ti ti-id text-xs text-slate-300"></i>
                    <span class="truncate">${bus.driver}</span>
                </div>
            </div>
        `;
    }

    // 3B: summary widgets population
    function renderBusSummaryCards() {
        const grid = document.getElementById('bus-summary-cards-grid');
        if (!grid) return;
        grid.innerHTML = '';
        
        busCardsData.forEach(bus => {
            grid.innerHTML += getBusSummaryCardHtml(bus);
        });
    }

    // Helper: Forecast table gap & indicators
    function getPredictionRowHtml(row) {
        let gapHtml = '';
        let actionHtml = '';
        let rowClass = 'hover:bg-slate-50/50 transition border-b border-slate-100';

        if (row.gap === 0) {
            gapHtml = `<div class="flex items-center justify-center gap-1 text-slate-400 font-bold"><i class="ti ti-check text-emerald-600 text-base shrink-0"></i> —</div>`;
            actionHtml = `<span class="text-slate-400 font-bold flex items-center gap-1"><i class="ti ti-check text-emerald-600"></i> ${row.action}</span>`;
        } else if (row.gap === 1) {
            gapHtml = `<div class="flex items-center justify-center gap-1 text-[#BA7517] font-black"><i class="ti ti-alert-circle text-base animate-pulse shrink-0"></i> +1</div>`;
            actionHtml = `<span class="text-[#0C447C] font-black">${row.action}</span>`;
        } else if (row.gap >= 2) {
            gapHtml = `<div class="flex items-center justify-center gap-1 text-[#E24B4A] font-black"><i class="ti ti-alert-triangle text-base animate-bounce shrink-0"></i> +${row.gap}</div>`;
            actionHtml = `<span class="text-[#A32D2D] font-black">${row.action}</span>`;
            rowClass = 'bg-red-50/60 border-l-4 border-[#E24B4A] hover:bg-red-50/80 transition';
        } else if (row.gap === -1) {
            gapHtml = `<div class="flex items-center justify-center gap-1 text-teal-600 font-black"><i class="ti ti-info-circle text-base shrink-0"></i> -1</div>`;
            actionHtml = `<span class="text-teal-600 font-bold">${row.action}</span>`;
        }

        return `
            <tr class="${rowClass}">
                <td class="px-3 py-2.5 font-bold">${row.slot}</td>
                <td class="px-3 py-2.5 font-extrabold text-[#003F87]">${row.predPax} pax</td>
                <td class="px-3 py-2.5 font-bold">${row.recBuses}</td>
                <td class="px-3 py-2.5 font-bold">${row.schedBuses}</td>
                <td class="px-3 py-2.5 text-center">${gapHtml}</td>
                <td class="px-3 py-2.5">${actionHtml}</td>
            </tr>
        `;
    }

    // 4A: Forecast table population
    function renderForecastTable() {
        const tbody = document.getElementById('forecast-schedule-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!forecastData || forecastData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-3 py-5 text-center text-slate-400 font-bold">
                        Forecast recommendations deferred until reliable demand and TripLog foundation data are available.
                    </td>
                </tr>
            `;
            return;
        }

        forecastData.forEach(row => {
            tbody.innerHTML += getPredictionRowHtml(row);
        });
    }

    // 3D: Driver performance rankings population
    function renderDriverPerformanceTable() {
        const tbody = document.getElementById('driver-performance-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!driverPerformanceData || driverPerformanceData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="12" class="px-5 py-4 text-center text-slate-400">No driver operation records for the selected period</td></tr>`;
            return;
        }

        driverPerformanceData.forEach((driver, idx) => {
            let rankBadge = '';
            if (idx === 0) {
                rankBadge = `<span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#EF9F27] text-[10px] font-black text-[#633806]" title="Rank 1 Gold">#1</span>`;
            } else if (idx === 1) {
                rankBadge = `<span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D3D1C7] text-[10px] font-black text-[#444441]" title="Rank 2 Silver">#2</span>`;
            } else if (idx === 2) {
                rankBadge = `<span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#F5C4B3] text-[10px] font-black text-[#712B13]" title="Rank 3 Bronze">#3</span>`;
            } else {
                rankBadge = `<span class="flex h-6 w-6 items-center justify-center text-slate-400 text-xs font-extrabold">#${idx + 1}</span>`;
            }

            let incidentHtml = `<span class="inline-flex rounded-full bg-rose-50 border border-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-600">${driver.incidents} alert</span>`;
            if (parseInt(driver.incidents) === 0) {
                incidentHtml = `<span class="text-[#639922] font-extrabold flex items-center justify-end gap-1"><i class="ti ti-check text-base"></i> None</span>`;
            }

            const capacity = driver.capacity || 45;
            const peakLoad = parseInt(driver.peakLoad || 0);
            const tripsRun = parseInt(driver.tripsRun ?? driver.trips ?? 0);
            const completedTrips = parseInt(driver.completedTrips || 0);
            const ongoingTrips = parseInt(driver.ongoingTrips || 0);
            const dispatchedTrips = parseInt(driver.dispatchedTrips || 0);
            const cancelledTrips = parseInt(driver.cancelledTrips || 0);
            const operationalScore = driver.operationalScore === null || driver.operationalScore === undefined
                ? null
                : Math.max(0, Math.min(100, parseInt(driver.operationalScore, 10)));
            let operationalScoreHtml = `<span class="text-slate-400">No data</span>`;
            if (operationalScore !== null && !Number.isNaN(operationalScore)) {
                const scoreClass = operationalScore >= 90
                    ? 'text-[#639922]'
                    : operationalScore >= 70
                        ? 'text-[#B45309]'
                        : 'text-[#E24B4A]';
                operationalScoreHtml = `<span class="${scoreClass} font-extrabold" title="Accident/Breakdown incident score; cancellations excluded">${operationalScore}</span>`;
            }
            let peakLoadHtml = `<span class="text-slate-500">${peakLoad}</span>`;
            if (peakLoad >= capacity) {
                peakLoadHtml = `<span class="text-rose-600 font-extrabold">${peakLoad} <span class="text-[9px] font-bold bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded-full uppercase ml-1">Full</span></span>`;
            }

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="px-5 py-3.5">${rankBadge}</td>
                <td class="px-5 py-3.5 font-bold">${driver.name}</td>
                <td class="px-5 py-3.5 font-mono text-slate-600">${driver.bus}</td>
                <td class="px-5 py-3.5 font-bold text-[#003F87]">${driver.route}</td>
                <td class="px-5 py-3.5 font-extrabold text-[#003F87]">${tripsRun}</td>
                <td class="px-5 py-3.5">${completedTrips}</td>
                <td class="px-5 py-3.5">${ongoingTrips}</td>
                <td class="px-5 py-3.5">${dispatchedTrips}</td>
                <td class="px-5 py-3.5">${cancelledTrips}</td>
                <td class="px-5 py-3.5">${peakLoadHtml}</td>
                <td class="px-5 py-3.5">${operationalScoreHtml}</td>
                <td class="px-5 py-3.5 text-right">${incidentHtml}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 2B: Route comparison table population
    function renderRouteComparisonTable() {
        const tbody = document.getElementById('route-comparison-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!routeComparisonData || routeComparisonData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-4 text-center text-slate-400">No route comparison data</td></tr>`;
            return;
        }

        let totalTripsRun = 0;
        let totalCompleted = 0;
        let totalOngoing = 0;
        let totalDispatched = 0;
        let totalCancelled = 0;

        routeComparisonData.forEach(r => {
            const tripsRun = parseInt(r.tripsRun ?? r.trips ?? 0);
            const completedTrips = parseInt(r.completedTrips || 0);
            const ongoingTrips = parseInt(r.ongoingTrips || 0);
            const dispatchedTrips = parseInt(r.dispatchedTrips || 0);
            const cancelledTrips = parseInt(r.cancelledTrips || 0);
            const completionRateValue = Number(r.completionRate);
            const completionRate = r.completionRate !== null
                && r.completionRate !== undefined
                && r.completionRate !== 'No data'
                && Number.isFinite(completionRateValue)
                ? `${completionRateValue}%`
                : 'No data';

            totalTripsRun += tripsRun;
            totalCompleted += completedTrips;
            totalOngoing += ongoingTrips;
            totalDispatched += dispatchedTrips;
            totalCancelled += cancelledTrips;
            
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="py-3 font-bold text-[#003F87]">${r.route}</td>
                <td class="py-3">${tripsRun}</td>
                <td class="py-3">${completedTrips}</td>
                <td class="py-3">${ongoingTrips}</td>
                <td class="py-3">${dispatchedTrips}</td>
                <td class="py-3">${cancelledTrips}</td>
                <td class="py-3 text-right"><span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87]">${completionRate}</span></td>
            `;
            tbody.appendChild(tr);
        });

        // Add footer row
        const totalCompletionDenominator = totalCompleted + totalCancelled;
        const totalCompletionRate = totalCompletionDenominator > 0
            ? `${Math.round((totalCompleted / totalCompletionDenominator) * 100)}%`
            : 'No data';
        const footerTr = document.createElement('tr');
        footerTr.className = "font-bold bg-slate-50 border-t border-slate-200 footer-row text-slate-900";
        footerTr.innerHTML = `
            <td class="py-3 pl-2">Totals</td>
            <td class="py-3">${totalTripsRun}</td>
            <td class="py-3">${totalCompleted}</td>
            <td class="py-3">${totalOngoing}</td>
            <td class="py-3">${totalDispatched}</td>
            <td class="py-3">${totalCancelled}</td>
            <td class="py-3 text-right pr-2">${totalCompletionRate}</td>
        `;
        tbody.appendChild(footerTr);
    }

    // 2D: Top stops passenger flow table
    function renderTopStopsTable() {
        const tbody = document.getElementById('top-stops-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!stopBoardingData || stopBoardingData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-slate-400">No stop demand data available</td></tr>`;
            return;
        }

        stopBoardingData.slice(0, 5).forEach(stop => {
            const netVal = parseInt(stop.net);
            const netColor = netVal >= 0 ? 'text-[#639922]' : 'text-[#E24B4A]';
            const netSign = netVal >= 0 ? '+' : '';

            const routesServed = stop.routes || 'No data';

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="py-2.5 font-bold">${stop.name}</td>
                <td class="py-2.5 text-[#003F87] font-bold">${routesServed}</td>
                <td class="py-2.5">${stop.boarding} requests</td>
                <td class="py-2.5">${stop.alighting} requests</td>
                <td class="py-2.5 text-right ${netColor} font-bold">${netSign}${netVal}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    // 1: KPI elements update
    function updateAnalyticsKPIs() {
        if (!kpisData) return;

        const setElementText = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        const setElementHtml = (id, html) => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = html;
        };

        setElementText('kpi-pax-today', kpisData.total_pax_today ?? 0);
        setElementHtml('kpi-pax-today-trend', `<i class="ti ti-users"></i> ${kpisData.pax_change_yesterday || 'Recorded boarded events in selected period'}`);

        setElementText('kpi-pax-week', kpisData.pax_this_week ?? 0);
        setElementHtml('kpi-pax-week-trend', `<i class="ti ti-calendar"></i> ${kpisData.pax_change_last_week || 'Recorded boarded events in selected period'}`);

        setElementText('kpi-avg-pax', kpisData.avg_pax_trip ?? 0);
        setElementHtml('kpi-avg-pax-trend', `<i class="ti ti-chart-bar"></i> ${kpisData.avg_pax_trip_change || 'Average peak load per actual trip in selected period'}`);

        setElementText('kpi-trips-completed', kpisData.trips_completed || '0');

        setElementText('kpi-trips-completed-percent', 'Actual operations');
        setElementText('kpi-trips-completed-sub', 'completed trips in selected period');

        setElementText('kpi-fleet-util', `${kpisData.fleet_util || 0}%`);
        setElementText('kpi-fleet-util-sub', `${kpisData.buses_in_service ?? kpisData.active_buses ?? 0} of ${kpisData.total_buses || 0} buses operated in selected period`);

        // Update visual circle stroke dasharray if possible
        const circleSvg = document.querySelector('#screen-analytics svg circle[stroke="#003F87"]');
        if (circleSvg) {
            const dashValue = Math.round((kpisData.fleet_util / 100) * 88);
            circleSvg.setAttribute('stroke-dasharray', `${dashValue} 88`);
        }

        setElementText('kpi-on-time-rate', kpisData.on_time_rate || 'Deferred');
        setElementText('kpi-on-time-sub', 'not schedule-status backed');

        const totalTripsRun = routeComparisonData
            ? routeComparisonData.reduce((sum, r) => sum + parseInt(r.tripsRun ?? r.trips ?? 0), 0)
            : 0;
        setElementText('doughnut-total-trips', totalTripsRun);

        // Dynamic rendering of trips-started legend
        const legendContainer = document.getElementById('hourly-chart-legend');
        if (legendContainer && routeComparisonData) {
            legendContainer.innerHTML = '';
            routeComparisonData.forEach((r, idx) => {
                const color = r.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4];
                const tripsRun = parseInt(r.tripsRun ?? r.trips ?? 0);
                legendContainer.innerHTML += `
                    <span class="flex items-center gap-1">
                        <span class="h-2.5 w-2.5 rounded" style="background-color: ${color};"></span>
                        ${r.route} - ${tripsRun} trips run
                    </span>
                `;
            });
        }

        // Dynamic rendering of Route Doughnut Legend
        const doughnutLegend = document.getElementById('route-doughnut-legend');
        if (doughnutLegend && routeComparisonData) {
            doughnutLegend.innerHTML = '';
            routeComparisonData.forEach((r, idx) => {
                const color = r.color || ['#003F87', '#639922', '#BA7517', '#E24B4A'][idx % 4];
                const tripsRun = parseInt(r.tripsRun ?? r.trips ?? 0);
                const percentage = totalTripsRun > 0 ? Math.round((tripsRun / totalTripsRun) * 100) : 0;
                doughnutLegend.innerHTML += `
                    <div class="text-center">
                        <span class="flex items-center gap-1.5 justify-center">
                            <span class="h-2 w-2 rounded-full" style="background-color: ${color};"></span>
                            ${r.route}
                        </span>
                        <p class="text-slate-800 font-extrabold mt-0.5">${tripsRun} trips (${percentage}%)</p>
                        <span class="text-slate-400">No comparison data</span>
                    </div>
                `;
            });
        }

        // Dynamic rendering of Heatmap Insights
        if (heatmapData) {
            const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const hours = ["5 AM", "6 AM", "7 AM", "8 AM", "9 AM", "10 AM", "11 AM", "12 PM", "1 PM", "2 PM", "3 PM", "4 PM", "5 PM", "6 PM", "7 PM", "8 PM", "9 PM", "10 PM"];
            let maxPax = -1, maxDay = "", maxHour = "";
            let minPax = Infinity, minDay = "", minHour = "";
            
            daysOfWeek.forEach(day => {
                const row = heatmapData[day];
                if (row) {
                    row.forEach((pax, hrIdx) => {
                        if (pax > maxPax) {
                            maxPax = pax;
                            maxDay = day;
                            maxHour = hours[hrIdx];
                        }
                        if (pax < minPax) {
                            minPax = pax;
                            minDay = day;
                            minHour = hours[hrIdx];
                        }
                    });
                }
            });

            const insightsContainer = document.getElementById('heatmap-insights-container');
            if (insightsContainer) {
                insightsContainer.innerHTML = `
                    <span class="bg-[#FDF2F2] text-[#E24B4A] border border-[#E24B4A]/10 px-2.5 py-0.5 rounded-full uppercase">Highest: ${maxDay} ${maxHour} · ${maxPax} pax</span>
                    <span class="bg-slate-50 border border-slate-200 px-2.5 py-0.5 rounded-full text-slate-500 uppercase">Lowest: ${minDay} ${minHour} · ${minPax} pax</span>
                `;
            }
        }

        // Dynamic rendering of Passenger load timeline legend
        const timelineLegend = document.getElementById('pax-load-timeline-legend');
        if (timelineLegend && typeof tripData !== 'undefined' && tripData) {
            const activePlates = [...new Set(tripData.map(t => t.plate))].slice(0, 4);
            const timelineConfig = [
                { color: '#003F87', style: 'circle', dash: [], radius: 4 },
                { color: '#639922', style: 'rect', dash: [6, 3], radius: 4.5 },
                { color: '#BA7517', style: 'triangle', dash: [2, 4], radius: 5 },
                { color: '#E24B4A', style: 'rectRot', dash: [10, 5], radius: 5 }
            ];
            
            timelineLegend.innerHTML = '';
            activePlates.forEach((plate, i) => {
                const config = timelineConfig[i] || timelineConfig[0];
                const borderStyle = config.dash.length > 0 ? 'border-dashed' : 'border-solid';
                timelineLegend.innerHTML += `
                    <span class="flex items-center gap-1">
                        <span class="h-2.5 w-4 rounded border-t-2 border-b-2 ${borderStyle} border-white" style="background-color: ${config.color};"></span>
                        ${plate}
                    </span>
                `;
            });
        }

        // Dynamic Route filter select options
        const filterSelect = document.getElementById('trip-route-filter');
        if (filterSelect && routeComparisonData) {
            const currentSelected = filterSelect.value;
            filterSelect.innerHTML = '<option value="all">All Routes</option>';
            routeComparisonData.forEach(r => {
                filterSelect.innerHTML += `<option value="${r.route}">${r.route}</option>`;
            });
            if (currentSelected && filterSelect.querySelector(`option[value="${currentSelected}"]`)) {
                filterSelect.value = currentSelected;
            }
        }

        // Dynamic Forecast Tabs
        const tabsContainer = document.getElementById('forecast-route-tabs');
        if (tabsContainer && routeComparisonData) {
            tabsContainer.innerHTML = `<button onclick="switchPredictionRoute('all')" data-pred-route-tab="all" class="bg-[#003F87] text-white px-2 py-0.5 rounded transition uppercase cursor-pointer">All</button>`;
            routeComparisonData.forEach(r => {
                const label = r.route.replace('Route ', '');
                tabsContainer.innerHTML += `
                    <button onclick="switchPredictionRoute('${r.route}')" data-pred-route-tab="${r.route}" class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded hover:bg-slate-200 transition uppercase cursor-pointer">${label}</button>
                `;
            });
    }
}
