    // 3A: Trip table filter implementation
    function filterTripTableByRoute() {
        const filterSelect = document.getElementById('trip-route-filter');
        if (!filterSelect) return;
        const val = filterSelect.value;
        const rows = document.querySelectorAll('#trip-pax-tbody tr');

        rows.forEach(row => {
            if (row.classList.contains('footer-row')) return;
            const route = row.getAttribute('data-trip-route');
            if (val === 'all' || route === val) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    // 3A: Real CSV exporter from tripData array
    function exportCSVDataMock() {
        if (!window.tripData || window.tripData.length === 0) {
            GoPasigUI.alert("No trip data available to export today.");
            return;
        }

        const headers = ["Trip #", "Bus Plate", "Driver", "Route", "Departure", "Arrival", "Pax Boarded", "Pax Alighted", "Peak Load", "Capacity %"];
        const rows = window.tripData.map(t => [
            t.tripNo || '',
            t.plate || '',
            t.driver || '',
            t.route || '',
            t.depTime || '',
            t.arrTime || '',
            t.boarded ?? 0,
            t.alighted ?? 0,
            t.peakLoad ?? 0,
            (t.capacity ?? 0) + "%"
        ]);

        const csvContent = "\uFEFF" + [
            headers.join(","),
            ...rows.map(e => e.map(val => `"${String(val).replace(/"/g, '""')}"`).join(","))
        ].join("\n");

        try {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `passenger_trips_data_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (err) {
            console.error("Failed to export CSV:", err);
            GoPasigUI.alert("An error occurred while generating the CSV file.");
        }
    }

    // 4B: Route prediction switch tabs
    function switchPredictionRoute(routeName) {
        const tabBtns = document.querySelectorAll('[data-pred-route-tab]');
        tabBtns.forEach(btn => {
            btn.className = "bg-slate-100 text-slate-600 px-2 py-0.5 rounded hover:bg-slate-200 transition uppercase cursor-pointer";
        });

        const activeBtn = document.querySelector(`[data-pred-route-tab="${routeName}"]`);
        if (activeBtn) {
            activeBtn.className = "bg-[#003F87] text-white px-2 py-0.5 rounded transition uppercase cursor-pointer";
        }

        const volEl = document.getElementById('pred-route-vol');
        const recEl = document.getElementById('pred-route-rec');
        const busiestEl = document.getElementById('pred-route-busiest');

        if (!volEl || !recEl || !busiestEl) return;

        // Determine theme coloring for the busiest card dynamically
        let badgeColorClass = 'bg-[#FEF7ED] border border-[#BA7517]/10 p-2.5 rounded-lg text-[#8F530B] font-extrabold text-[11px] shrink-0 text-center uppercase tracking-wider';
        if (routeName !== 'all' && typeof routeComparisonData !== 'undefined' && routeComparisonData) {
            const idx = routeComparisonData.findIndex(r => r.route === routeName);
            if (idx !== -1) {
                const colors = [
                    'bg-[#E6F1FB] border border-[#003F87]/10 text-[#003F87]',
                    'bg-[#E8F4E0] border border-[#639922]/10 text-[#639922]',
                    'bg-[#FEF7ED] border border-[#BA7517]/10 text-[#BA7517]',
                    'bg-[#FDF2F2] border border-[#E24B4A]/10 text-[#E24B4A]'
                ];
                const colorClass = colors[idx % colors.length];
                badgeColorClass = `${colorClass} p-2.5 rounded-lg font-extrabold text-[11px] shrink-0 text-center uppercase tracking-wider`;
            }
        }

        busiestEl.className = badgeColorClass;

        if (typeof predictionRouteData !== 'undefined' && predictionRouteData && predictionRouteData[routeName]) {
            const data = predictionRouteData[routeName];
            volEl.textContent = data.vol;
            recEl.textContent = data.rec;
            busiestEl.textContent = data.busiest;
        } else {
            // ISSUE-047 FIX: Removed hardcoded static text fallbacks for specific routes (Route A, B, C).
            // Now shows clear 'N/A' if prediction data is not available.
            volEl.textContent = 'N/A';
            recEl.textContent = 'N/A';
            busiestEl.textContent = 'No prediction data available';
        }
    }

    // 5A & 5B: interactive forms handlers & log history update
    function handleGenerateReport(event) {
        event.preventDefault();

        const form = event.target;
        const btn = document.getElementById('generate-report-btn');
        const btnText = document.getElementById('report-btn-text');
        
        if (!btn || !btnText) return;
        
        // Retrieve selections
        const reportType = form.elements['report_type'].value;
        const formatRadio = form.elements['format'];
        let formatVal = 'PDF';
        if (formatRadio) {
            formatVal = formatRadio[0].checked ? 'PDF' : 'CSV';
        }
        
        let typeLabel = 'Ridership Summary';
        let iconClass = 'ti ti-users';
        let bgClass = 'bg-[#E6F1FB] text-[#003F87]';
        
        if (reportType === 'fleet') {
            typeLabel = 'Fleet Utilization';
            iconClass = 'ti ti-bus';
        } else if (reportType === 'route') {
            typeLabel = 'Route Performance';
            iconClass = 'ti ti-route';
            bgClass = 'bg-[#E8F4E0] text-[#639922]';
        } else if (reportType === 'driver') {
            typeLabel = 'Driver Performance';
            iconClass = 'ti ti-id';
            bgClass = 'bg-[#FEF7ED] text-[#BA7517]';
        } else if (reportType === 'dispatch') {
            typeLabel = 'Dispatch Analysis';
            iconClass = 'ti ti-chart-bar';
        } else if (reportType === 'maintenance') {
            typeLabel = 'Maintenance Log';
            iconClass = 'ti ti-tool';
        }

        // Show mock generating spinner loading state (3 seconds delay)
        btn.disabled = true;
        btnText.textContent = 'Generating...';
        btn.classList.add('opacity-75');

        setTimeout(() => {
            // Update document live preview panel (5B)
            const previewTitle = document.getElementById('preview-report-title');
            if (previewTitle) {
                previewTitle.textContent = `${typeLabel} · Today`;
            }

            // Append newly generated report to history logs list
            const historyContainer = document.getElementById('reports-history-list');
            if (historyContainer) {
                const newRow = document.createElement('div');
                newRow.className = "flex items-center justify-between border-b border-slate-50 pb-2 hover:bg-slate-50/50 p-2.5 rounded-lg transition duration-200";
                
                const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const dateStr = new Date().toLocaleDateString([], { month: 'short', day: 'numeric' });
                const size = formatVal === 'PDF' ? '234 KB' : '48 KB';

                newRow.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg ${bgClass}"><i class="${iconClass} text-base"></i></span>
                        <div class="leading-none">
                            <h4 class="text-xs font-black text-slate-900">${typeLabel} · ${dateStr}</h4>
                            <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">${formatVal} · ${size} · ${dateStr}, ${timestamp}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button onclick="GoPasigUI.alert('Downloading Report...')" class="p-1 text-slate-400 hover:text-[#003F87] cursor-pointer" title="Download"><i class="ti ti-download text-sm"></i></button>
                        <button onclick="this.closest('.flex').remove()" class="p-1 text-slate-400 hover:text-[#E24B4A] cursor-pointer" title="Delete"><i class="ti ti-trash text-sm"></i></button>
                    </div>
                `;
                historyContainer.insertBefore(newRow, historyContainer.firstChild);
            }

            // Reset button state
            btn.disabled = false;
            btnText.textContent = 'Generate Report';
            btn.classList.remove('opacity-75');

            GoPasigUI.alert(`Success! "${typeLabel}" generated successfully in ${formatVal} format.`);
        }, 3000);
    }

    // Setup segmented button controls and pill selection checkmarks at start
    function bindAnalyticsInteractiveEvents() {
        // Date range segmented buttons toggle active class + AJAX request
        const dateSegment = document.getElementById('date-range-segment');
        if (dateSegment) {
            dateSegment.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    dateSegment.querySelectorAll('button').forEach(b => {
                        b.className = "px-3 py-1 flex-1 transition hover:text-slate-800 cursor-pointer";
                    });
                    this.className = "bg-white text-[#003F87] rounded-md px-3 py-1 flex-1 transition shadow-sm cursor-pointer";
                    
                    // Determine date range based on button text
                    const buttonText = this.textContent.trim();
                    let startDate, endDate;
                    const today = new Date();
                    
                    if (buttonText === 'Today') {
                        startDate = today.toISOString().split('T')[0];
                        endDate = today.toISOString().split('T')[0];
                    } else if (buttonText === 'Yesterday') {
                        const yesterday = new Date(today);
                        yesterday.setDate(yesterday.getDate() - 1);
                        startDate = yesterday.toISOString().split('T')[0];
                        endDate = yesterday.toISOString().split('T')[0];
                    } else if (buttonText === 'Weekly') {
                        const weekAgo = new Date(today);
                        weekAgo.setDate(weekAgo.getDate() - 7);
                        startDate = weekAgo.toISOString().split('T')[0];
                        endDate = today.toISOString().split('T')[0];
                    } else if (buttonText === 'Monthly') {
                        const monthAgo = new Date(today);
                        monthAgo.setDate(monthAgo.getDate() - 30);
                        startDate = monthAgo.toISOString().split('T')[0];
                        endDate = today.toISOString().split('T')[0];
                    }
                    
                    // Send AJAX request to fetch filtered analytics data
                    if (startDate && endDate) {
                        fetch(`/admin/api/analytics?start=${startDate}&end=${endDate}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Update global window variables with new data
                                    window.kpisData = data.kpis;
                                    window.hourlyRidershipData = data.hourlyRidership;
                                    window.routeComparisonData = data.routeComparison;
                                    window.heatmapData = data.heatmap;
                                    window.stopBoardingData = data.stopBoarding;
                                    window.tripData = data.tripPaxTable || [];
                                    window.busSummaryCardsData = data.busSummaryCards;
                                    window.forecastData = data.forecastTable;
                                    window.driverPerformanceData = data.driverPerformance;
                                    window.historicalTrendData = data.historicalTrend;
                                    window.busCapacityLimit = data.busCapacityLimit || 45;
                                    
                                    // Re-initialize all charts with new data
                                    initAnalyticsDashboard();
                                }
                            })
                            .catch(error => console.error('Analytics data fetch error:', error));
                    }
                });
            });
        }

        // Report type radio selectors hover / checkmarks styling highlights
        document.querySelectorAll('input[name="report_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('input[name="report_type"]').forEach(r => {
                    const label = r.closest('label');
                    if (label) {
                        label.className = "border border-slate-200 rounded-lg p-2.5 flex items-center gap-2 cursor-pointer hover:bg-slate-50 transition select-none";
                        const icon = label.querySelector('i');
                        if (icon) {
                            icon.className = icon.className.replace('text-[#0C447C]', 'text-slate-400');
                        }
                    }
                });
                
                if (this.checked) {
                    const activeLabel = this.closest('label');
                    if (activeLabel) {
                        activeLabel.className = "border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C] rounded-lg p-2.5 flex items-center gap-2 cursor-pointer transition select-none";
                        const icon = activeLabel.querySelector('i');
                        if (icon) {
                            icon.className = icon.className.replace('text-slate-400', 'text-[#0C447C]');
                        }
                    }
                }
            });
        });

        // Pill checkboxes active background color toggle highlights
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            const label = checkbox.closest('label');
            if (label && label.textContent.includes('Route')) {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        label.className = "rounded-full bg-[#003F87] text-white px-3.5 py-1 flex items-center gap-1.5 cursor-pointer border border-[#003F87] transition select-none";
                    } else {
                        label.className = "rounded-full bg-white text-slate-500 px-3.5 py-1 flex items-center gap-1.5 cursor-pointer border border-slate-200 hover:bg-slate-50 transition select-none";
                    }
                });
            }
        });

        // Setup Submit Handler for Report builder form
        const reportForm = document.getElementById('report-builder-form');
        if (reportForm) {
            reportForm.addEventListener('submit', handleGenerateReport);
        }
    }

