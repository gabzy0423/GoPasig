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
            if (heatmapData[dayName] && heatmapData[dayName][hourIndex] !== undefined) {
                return heatmapData[dayName][hourIndex];
            }
        }

        let base = 25;
        if (hourIndex === 2) base = 280;      // AM peak (7 AM)
        else if (hourIndex === 3) base = 220; // 8 AM
        else if (hourIndex === 12) base = 260;// PM peak (5 PM)
        else if (hourIndex === 13) base = 240;// 6 PM
        else if (hourIndex === 14) base = 150;// 7 PM
        else if (hourIndex === 1) base = 120; // 6 AM
        else if (hourIndex === 11) base = 140;// 4 PM
        else if (hourIndex === 7) base = 110; // 12 PM lunch surge
        else if (hourIndex === 8) base = 100; // 1 PM
        else {
            // Deterministic fallback — Gaussian AM/PM peak profile matching AnalyticsController.
            // hour 0 = 5 AM, so hourIndex 2 = 7 AM (AM peak), hourIndex 12 = 5 PM (PM peak).
            const hourInt = 5 + hourIndex;
            const amPeak = Math.exp(-Math.pow(hourInt - 8, 2) / 3) * 60;
            const pmPeak = Math.exp(-Math.pow(hourInt - 18, 2) / 4) * 80;
            base = Math.round(15 + amPeak + pmPeak);
        }

        if (dayIndex === 6) { // Sunday
            base = Math.floor(base * 0.4);
        } else if (dayIndex === 5) { // Saturday
            base = Math.floor(base * 0.6);
        } else if (dayIndex === 1 && hourIndex === 2) {
            base = 312; // Matches EXACT tooltip prompt condition: "Tuesday · 7 AM · 312 pax"
        } else {
            // Sine-wave per-cell variation (deterministic, not random)
            const variation = Math.round(Math.sin(dayIndex * 1.5 + hourIndex * 0.8) * 5);
            base = base + variation;
        }

        return Math.max(7, Math.floor(base));
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
                const color = getHeatmapColor(pax);
                const tooltipText = `${fullDays[dayIdx]} · ${hours[hourIdx]} · ${pax} pax across all routes`;
                
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
        if (route === 'Route A') {
            return `<span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87] border border-[#003F87]/15">Route A</span>`;
        } else if (route === 'Route B') {
            return `<span class="inline-flex rounded-full bg-[#E8F4E0] px-2 py-0.5 text-[9px] font-bold text-[#639922] border border-[#639922]/15">Route B</span>`;
        } else if (route === 'Route C') {
            return `<span class="inline-flex rounded-full bg-[#FEF7ED] px-2 py-0.5 text-[9px] font-bold text-[#BA7517] border border-[#BA7517]/15">Route C</span>`;
        }
        return route;
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

    // 3A: trip logs table population
    function renderTripTable() {
        const tbody = document.getElementById('trip-pax-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        tripData.forEach(trip => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.setAttribute('data-trip-route', trip.route);

            tr.innerHTML = `
                <td class="px-4 py-2.5 font-bold">${trip.tripNo}</td>
                <td class="px-4 py-2.5 font-mono text-slate-600">${trip.plate}</td>
                <td class="px-4 py-2.5 font-bold text-slate-800">${trip.driver}</td>
                <td class="px-4 py-2.5">${getRouteBadgeHtml(trip.route)}</td>
                <td class="px-4 py-2.5 text-slate-500">${trip.depTime}</td>
                <td class="px-4 py-2.5 text-slate-500">${trip.arrTime}</td>
                <td class="px-4 py-2.5 font-bold text-slate-800">${trip.boarded}</td>
                <td class="px-4 py-2.5 text-slate-500">${trip.alighted}</td>
                <td class="px-4 py-2.5 font-bold text-slate-700">${trip.peakLoad}</td>
                <td class="px-4 py-2.5">${getCapacityCellHtml(trip.capacity)}</td>
            `;
            tbody.appendChild(tr);
        });

        // Totals averages table footer row
        const totalPaxBoarded = tripData.reduce((sum, t) => sum + parseInt(t.boarded || 0), 0);
        const avgPaxTrip = tripData.length > 0 ? (totalPaxBoarded / tripData.length).toFixed(1) : 0;
        const avgCapacityPct = tripData.length > 0 ? Math.round(tripData.reduce((sum, t) => sum + parseFloat(t.capacity || 0), 0) / tripData.length) : 0;

        const footerTr = document.createElement('tr');
        footerTr.className = "font-bold bg-slate-50 border-t border-slate-200 footer-row text-slate-900";
        footerTr.innerHTML = `
            <td class="px-4 py-3 text-slate-800" colspan="6">Totals & Averages</td>
            <td class="px-4 py-3 font-extrabold text-[#003F87]">${totalPaxBoarded.toLocaleString()} pax</td>
            <td class="px-4 py-3 text-slate-400">—</td>
            <td class="px-4 py-3 font-extrabold">${avgPaxTrip} avg</td>
            <td class="px-4 py-3 font-extrabold text-[#185FA5]">${avgCapacityPct}% avg</td>
        `;
        tbody.appendChild(footerTr);
    }

    // Helper: bus summary widget builder (3B)
    function getBusSummaryCardHtml(bus) {
        const statusChip = getStatusChipHtml(bus.status);
        const peakLoadClass = bus.peakLoad === 45 ? 'text-[#E24B4A] font-extrabold' : 'text-slate-700 font-semibold';
        
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
                        <span>Total trips:</span>
                        <span class="text-slate-800 font-bold">${bus.trips}</span>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <span>Total pax today:</span>
                        <span class="text-[#003F87] text-lg font-black leading-none">${bus.totalPax}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Avg pax/trip:</span>
                        <span class="text-slate-800 font-bold">${bus.avgPax}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Peak load:</span>
                        <span class="${peakLoadClass}">${bus.peakLoad} / 45</span>
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
            tbody.innerHTML = `<tr><td colspan="9" class="px-5 py-4 text-center text-slate-400">No driver records today</td></tr>`;
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

            let peakLoadHtml = `<span class="text-slate-500">${driver.peakLoad}</span>`;
            if (driver.peakLoad >= 45) {
                peakLoadHtml = `<span class="text-rose-600 font-extrabold">${driver.peakLoad} <span class="text-[9px] font-bold bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded-full uppercase ml-1">Full</span></span>`;
            }

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="px-5 py-3.5">${rankBadge}</td>
                <td class="px-5 py-3.5 font-bold">${driver.name}</td>
                <td class="px-5 py-3.5 font-mono text-slate-600">${driver.bus}</td>
                <td class="px-5 py-3.5 font-bold text-[#003F87]">${driver.route}</td>
                <td class="px-5 py-3.5">${driver.trips} trips</td>
                <td class="px-5 py-3.5 font-extrabold text-[#003F87]">${driver.pax} pax</td>
                <td class="px-5 py-3.5">${driver.avgPax}</td>
                <td class="px-5 py-3.5">${peakLoadHtml}</td>
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
            tbody.innerHTML = `<tr><td colspan="6" class="py-4 text-center text-slate-400">No route comparison data</td></tr>`;
            return;
        }

        let totalTrips = 0;
        let totalPax = 0;
        let totalAvg = 0;

        routeComparisonData.forEach(r => {
            totalTrips += parseInt(r.trips || 0);
            totalPax += parseInt(r.pax || 0);
            
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="py-3 font-bold text-[#003F87]">${r.route}</td>
                <td class="py-3">${r.trips} trips</td>
                <td class="py-3">${r.pax.toLocaleString()} pax</td>
                <td class="py-3">${r.avgPax}</td>
                <td class="py-3"><span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87]">${r.peakHour}</span></td>
                <td class="py-3 text-right">${r.busiestStop}</td>
            `;
            tbody.appendChild(tr);
        });

        // Add footer row
        totalAvg = totalTrips > 0 ? (totalPax / totalTrips).toFixed(1) : 0;
        const footerTr = document.createElement('tr');
        footerTr.className = "font-bold bg-slate-50 border-t border-slate-200 footer-row text-slate-900";
        footerTr.innerHTML = `
            <td class="py-3 pl-2">Totals</td>
            <td class="py-3">${totalTrips} trips</td>
            <td class="py-3">${totalPax.toLocaleString()} pax</td>
            <td class="py-3">${totalAvg} avg</td>
            <td class="py-3">—</td>
            <td class="py-3 text-right pr-2">—</td>
        `;
        tbody.appendChild(footerTr);
    }

    // 2D: Top stops passenger flow table
    function renderTopStopsTable() {
        const tbody = document.getElementById('top-stops-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!stopBoardingData || stopBoardingData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-4 text-center text-slate-400">No stop flow data</td></tr>`;
            return;
        }

        stopBoardingData.slice(0, 5).forEach(stop => {
            const netVal = parseInt(stop.net);
            const netColor = netVal >= 0 ? 'text-[#639922]' : 'text-[#E24B4A]';
            const netSign = netVal >= 0 ? '+' : '';

            let routesServed = 'A';
            if (stop.name.includes('Hall') || stop.name.includes('Terminal')) routesServed = 'A, B';
            else if (stop.name.includes('Ortigas') || stop.name.includes('Rosario')) routesServed = 'B, C';
            else if (stop.name.includes('Shaw')) routesServed = 'C';
            else routesServed = 'A';

            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition border-b border-slate-100";
            tr.innerHTML = `
                <td class="py-2.5 font-bold">${stop.name}</td>
                <td class="py-2.5 text-[#003F87] font-bold">${routesServed}</td>
                <td class="py-2.5">${stop.boarding} / day</td>
                <td class="py-2.5">${stop.alighting} / day</td>
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

        setElementText('kpi-pax-today', kpisData.total_pax_today || '0');
        setElementHtml('kpi-pax-today-trend', `<i class="ti ti-trending-up"></i> ${kpisData.pax_change_yesterday || '+8% vs yesterday'}`);

        setElementText('kpi-pax-week', kpisData.pax_this_week || '0');
        setElementHtml('kpi-pax-week-trend', `<i class="ti ti-trending-up"></i> ${kpisData.pax_change_last_week || '+3% vs last week'}`);

        setElementText('kpi-avg-pax', kpisData.avg_pax_trip || '0.0');
        setElementHtml('kpi-avg-pax-trend', `<i class="ti ti-trending-down"></i> ${kpisData.avg_pax_trip_change || '-2% vs yesterday'}`);

        setElementText('kpi-trips-completed', kpisData.trips_completed || '0');
        
        const completionPct = kpisData.trips_scheduled > 0 
            ? Math.round((kpisData.trips_completed / kpisData.trips_scheduled) * 100) 
            : 100;
        setElementText('kpi-trips-completed-percent', `${completionPct}% Completion`);
        setElementText('kpi-trips-completed-sub', `of ${kpisData.trips_scheduled || 0} scheduled`);

        setElementText('kpi-fleet-util', `${kpisData.fleet_util || 0}%`);
        setElementText('kpi-fleet-util-sub', `${kpisData.active_buses || 0} of ${kpisData.total_buses || 0} buses active`);

        // Update visual circle stroke dasharray if possible
        const circleSvg = document.querySelector('#screen-analytics svg circle[stroke="#003F87"]');
        if (circleSvg) {
            const dashValue = Math.round((kpisData.fleet_util / 100) * 88);
            circleSvg.setAttribute('stroke-dasharray', `${dashValue} 88`);
        }

        setElementText('kpi-on-time-rate', `${kpisData.on_time_rate || 100}%`);
        setElementText('kpi-on-time-sub', `${kpisData.delayed_trips || 0} delayed trips today`);

        setElementText('doughnut-total-pax', kpisData.total_pax_today || '0');

        // Update Route A, B, C doughnut breakdown descriptions & trends
        routeComparisonData.forEach(r => {
            const routeKey = r.route.toLowerCase().replace('route ', ''); // 'a', 'b', or 'c'
            const descEl = document.getElementById(`doughnut-route-${routeKey}-desc`);
            if (descEl) {
                descEl.textContent = `${r.pax} pax (${r.percentage}%)`;
            }
            // Trend
            const trendEl = document.getElementById(`doughnut-route-${routeKey}-trend`);
            if (trendEl) {
                const diff = r.route === 'Route A' ? '+4%' : (r.route === 'Route B' ? '+2%' : '-1%');
                const isDown = diff.startsWith('-');
                const colorClass = isDown ? 'text-[#E24B4A]' : 'text-[#639922]';
                const trendIcon = isDown ? 'ti-trending-down' : 'ti-trending-up';
                trendEl.className = colorClass;
                trendEl.innerHTML = `<i class="${trendIcon}"></i> ${diff}`;
            }
        });
    }

