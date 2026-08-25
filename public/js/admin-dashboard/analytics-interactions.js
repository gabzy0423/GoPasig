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

function getTripLoadExportRows() {
        return (typeof tripData !== 'undefined' ? tripData : window.tripData) || [];
    }

function isAnalyticsDriverPerformanceVisible() {
        const screen = document.getElementById('screen-analytics-driver-performance');

        return !!screen && !screen.classList.contains('hidden');
    }

function updateLayoutExportButton(activeScreenName = null) {
        const btn = document.getElementById('layout-export-btn');
        if (!btn) return;

        const label = btn.querySelector('span');
        const icon = btn.querySelector('i');
        const rows = getTripLoadExportRows();
        const canExportTripLoad = rows.length > 0
            && (activeScreenName === 'analytics-driver-performance' || isAnalyticsDriverPerformanceVisible());

        btn.onclick = null;

        if (canExportTripLoad) {
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
            btn.dataset.exportEnabled = 'true';
            btn.title = 'Export Trip Load Records CSV for the selected period.';
            btn.className = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors sm:px-3 cursor-pointer';
            if (icon) icon.className = 'ti ti-download text-base text-slate-500';
            if (label) label.textContent = 'Export report';
            btn.onclick = () => exportCSVDataMock();

            return;
        }

        btn.disabled = true;
        btn.setAttribute('aria-disabled', 'true');
        btn.dataset.exportEnabled = 'false';
        btn.title = rows.length === 0
            ? 'No trip load records available to export for the selected period.'
            : 'Open Driver Performance to export Trip Load Records.';
        btn.className = 'inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-400 shadow-sm transition-colors sm:px-3';
        if (icon) icon.className = 'ti ti-download text-base text-slate-300';
        if (label) label.textContent = 'Export report';
    }

    window.updateLayoutExportButton = updateLayoutExportButton;

    // 3A: Real CSV exporter from tripData array
function downloadAnalyticsCSV(headers, rows, filename) {
        if (!rows || rows.length === 0) {
            GoPasigUI.alert("No exportable records available for the selected period.");
            return false;
        }

        const csvContent = "\uFEFF" + [
            headers.join(","),
            ...rows.map(e => e.map(val => `"${String(val ?? '').replace(/"/g, '""')}"`).join(","))
        ].join("\n");

        try {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            return true;
        } catch (err) {
            console.error("Failed to export CSV:", err);
            GoPasigUI.alert("An error occurred while generating the CSV file.");
            return false;
        }
    }

function exportCSVDataMock() {
        const exportRows = getTripLoadExportRows();
        if (exportRows.length === 0) {
            GoPasigUI.alert("No trip load records available to export for the selected period.");
            return;
        }

        const headers = ["Trip", "Driver", "Bus", "Route", "Status", "Started", "Ended", "Recorded boarded", "Recorded alighted", "Peak load"];
        const rows = exportRows.map(t => [
            t.tripNo || '',
            t.driver || '',
            t.plate || '',
            t.route || '',
            t.status || '',
            t.startedAt || '',
            t.endedAt || '',
            t.recordedBoarded ?? 0,
            t.recordedAlighted ?? 0,
            t.peakLoad ?? 0
        ]);

        downloadAnalyticsCSV(headers, rows, `trip_load_records_${new Date().toISOString().slice(0, 10)}.csv`);
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

function formatAnalyticsDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function getSelectedReportType() {
        return document.querySelector('input[name="report_type"]:checked')?.value || 'ridership';
    }

function getReportTypeLabel(reportType) {
        const labels = {
            ridership: 'Ridership Summary',
            fleet: 'Fleet Utilization',
            route: 'Route Performance',
            driver: 'Driver Performance',
            dispatch: 'Dispatch Analysis',
            maintenance: 'Maintenance Log'
        };

        return labels[reportType] || 'System Report';
    }

function getAnalyticsPreviewMetricSet(reportType) {
        const kpis = (typeof kpisData !== 'undefined' ? kpisData : window.kpisData) || {};
        const trips = (typeof tripData !== 'undefined' ? tripData : window.tripData) || [];
        const routes = (typeof routeComparisonData !== 'undefined' ? routeComparisonData : window.routeComparisonData) || [];
        const drivers = (typeof driverPerformanceData !== 'undefined' ? driverPerformanceData : window.driverPerformanceData) || [];
        const buses = (typeof busCardsData !== 'undefined' ? busCardsData : window.busCardsData) || [];
        const demand = (typeof demandForecastData !== 'undefined' ? demandForecastData : window.demandForecastData) || null;
        const maintenance = (typeof maintenanceLogRecordsData !== 'undefined' ? maintenanceLogRecordsData : window.maintenanceLogRecordsData) || [];
        const maintenanceSummary = (typeof maintenanceSummaryData !== 'undefined' ? maintenanceSummaryData : window.maintenanceSummaryData) || {};

        if (reportType === 'fleet') {
            return {
                aLabel: 'Buses Operated',
                aValue: `${kpis.buses_in_service ?? 0}`,
                bLabel: 'Trips',
                bValue: `${kpis.trips_completed ?? 0} completed`,
                cLabel: 'Util Rate',
                cValue: `${kpis.fleet_util ?? 0}%`,
                hasData: buses.length > 0 || trips.length > 0
            };
        }

        if (reportType === 'route') {
            const tripsRun = routes.reduce((sum, route) => sum + Number(route.tripsRun ?? route.trips ?? 0), 0);
            return {
                aLabel: 'Routes',
                aValue: `${routes.length}`,
                bLabel: 'Trips Run',
                bValue: `${tripsRun}`,
                cLabel: 'Recorded Boarded',
                cValue: `${kpis.total_pax_today ?? 0}`,
                hasData: routes.some(route => Number(route.tripsRun ?? route.trips ?? 0) > 0)
            };
        }

        if (reportType === 'driver') {
            const tripsRun = drivers.reduce((sum, driver) => sum + Number(driver.tripsRun ?? 0), 0);
            const boarded = drivers.reduce((sum, driver) => sum + Number(driver.recordedBoarded ?? driver.boarded ?? 0), 0);
            return {
                aLabel: 'Drivers',
                aValue: `${drivers.length}`,
                bLabel: 'Trips Run',
                bValue: `${tripsRun}`,
                cLabel: 'Recorded Boarded',
                cValue: `${boarded}`,
                hasData: drivers.some(driver => Number(driver.tripsRun ?? 0) > 0 || Number(driver.recordedBoarded ?? driver.boarded ?? 0) > 0)
            };
        }

        if (reportType === 'dispatch') {
            const summary = demand?.summary || {};
            return {
                aLabel: 'Forecast Rows',
                aValue: `${Array.isArray(demand?.rows) ? demand.rows.length : 0}`,
                bLabel: 'Expected Demand',
                bValue: `${summary.expected_commuters ?? 'No data'}`,
                cLabel: 'Advisory',
                cValue: summary.status_label || 'Deferred',
                hasData: Array.isArray(demand?.rows) && demand.rows.length > 0
            };
        }

        if (reportType === 'maintenance') {
            return {
                aLabel: 'Maintenance Records',
                aValue: `${maintenanceSummary.total ?? maintenance.length}`,
                bLabel: 'Completed',
                bValue: `${maintenanceSummary.completed ?? maintenance.filter(record => record.status === 'completed').length}`,
                cLabel: 'Scheduled/In Progress',
                cValue: `${maintenanceSummary.active ?? maintenance.filter(record => ['scheduled', 'in_progress'].includes(record.status)).length}`,
                hasData: maintenance.length > 0
            };
        }

        return {
            aLabel: 'Recorded Boarded',
            aValue: `${kpis.total_pax_today ?? 0}`,
            bLabel: 'Trips',
            bValue: `${kpis.trips_completed ?? 0} completed`,
            cLabel: 'Peak Load Rows',
            cValue: `${trips.length}`,
            hasData: trips.length > 0 || Number(kpis.total_pax_today ?? 0) > 0 || Number(kpis.trips_completed ?? 0) > 0
        };
    }

function getReportBuilderCsvPayload(reportType) {
        const period = window.analyticsReportingPeriod || { label: 'Today' };
        const periodLabel = period.label || 'Current period';
        const slug = `${getReportTypeLabel(reportType)} ${periodLabel}`
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');

        const withPeriod = (headers) => ['Reporting Period', ...headers];
        const stampRows = (rows) => rows.map(row => [periodLabel, ...row]);

        if (reportType === 'fleet') {
            const buses = (typeof busCardsData !== 'undefined' ? busCardsData : window.busCardsData) || [];
            return {
                headers: withPeriod(['Bus', 'Status', 'Driver', 'Trips Run', 'Passengers Handled', 'Avg Peak Load / Trip', 'Peak Load', 'Capacity', 'Avg Utilization %']),
                rows: stampRows(buses.map(bus => [
                    bus.plate || '',
                    bus.status || '',
                    bus.driver || '',
                    bus.trips ?? 0,
                    bus.totalPax ?? 0,
                    bus.avgPax ?? 0,
                    bus.peakLoad ?? 0,
                    bus.capacity ?? 45,
                    bus.avgCapacity ?? 0
                ])),
                filename: `${slug || 'fleet_utilization'}.csv`
            };
        }

        if (reportType === 'route') {
            const routes = (typeof routeComparisonData !== 'undefined' ? routeComparisonData : window.routeComparisonData) || [];
            return {
                headers: withPeriod(['Route', 'Trips Run', 'Completed', 'Ongoing', 'Dispatched', 'Cancelled', 'Completion Rate']),
                rows: stampRows(routes.map(route => [
                    route.route || '',
                    route.tripsRun ?? route.trips ?? 0,
                    route.completedTrips ?? 0,
                    route.ongoingTrips ?? 0,
                    route.dispatchedTrips ?? 0,
                    route.cancelledTrips ?? 0,
                    route.completionRate ?? 'No data'
                ])),
                filename: `${slug || 'route_performance'}.csv`
            };
        }

        if (reportType === 'driver') {
            const drivers = (typeof driverPerformanceData !== 'undefined' ? driverPerformanceData : window.driverPerformanceData) || [];
            return {
                headers: withPeriod(['Driver', 'Assigned Bus', 'Assigned Route', 'Trips Run', 'Completed', 'Ongoing', 'Dispatched', 'Cancelled', 'Peak Load', 'Operational Score', 'Incidents', 'Recorded Boarded']),
                rows: stampRows(drivers.map(driver => [
                    driver.name || '',
                    driver.bus || '',
                    driver.route || '',
                    driver.tripsRun ?? driver.trips ?? 0,
                    driver.completedTrips ?? 0,
                    driver.ongoingTrips ?? 0,
                    driver.dispatchedTrips ?? 0,
                    driver.cancelledTrips ?? 0,
                    driver.peakLoad ?? 0,
                    driver.operationalScore ?? 'No data',
                    driver.incidents ?? 0,
                    driver.recordedBoarded ?? driver.boarded ?? 0
                ])),
                filename: `${slug || 'driver_performance'}.csv`
            };
        }

        if (reportType === 'ridership') {
            const trips = (typeof tripData !== 'undefined' ? tripData : window.tripData) || [];
            return {
                headers: withPeriod(['Trip', 'Driver', 'Bus', 'Route', 'Status', 'Started', 'Ended', 'Recorded Boarded', 'Recorded Alighted', 'Peak Load']),
                rows: stampRows(trips.map(trip => [
                    trip.tripNo || '',
                    trip.driver || '',
                    trip.plate || '',
                    trip.route || '',
                    trip.status || '',
                    trip.startedAt || '',
                    trip.endedAt || '',
                    trip.recordedBoarded ?? 0,
                    trip.recordedAlighted ?? 0,
                    trip.peakLoad ?? 0
                ])),
                filename: `${slug || 'trip_load_records'}.csv`
            };
        }

        if (reportType === 'dispatch') {
            const forecast = (typeof forecastData !== 'undefined' ? forecastData : window.forecastData) || [];
            return {
                headers: withPeriod(['Route', 'Direction', 'Time Slot', 'Expected Demand', 'Confidence', 'Minimum Buses', 'Basis']),
                rows: stampRows(forecast.map(row => [
                    row.route_name || '',
                    row.direction_label || '',
                    row.time_slot || '',
                    row.expected_commuters ?? 'Insufficient history',
                    row.confidence_label || row.confidence || 'No data',
                    row.minimum_buses ?? 'Not issued',
                    row.basis || ''
                ])),
                filename: `${slug || 'dispatch_analysis'}.csv`
            };
        }

        if (reportType === 'maintenance') {
            const records = (typeof maintenanceLogRecordsData !== 'undefined' ? maintenanceLogRecordsData : window.maintenanceLogRecordsData) || [];
            return {
                headers: withPeriod(['Ticket', 'Bus', 'Type', 'Status', 'Scheduled', 'Completed', 'Technician', 'Inspector', 'Result', 'Roadworthy', 'Total Cost']),
                rows: stampRows(records.map(record => [
                    record.ticket || '',
                    record.bus || '',
                    record.type || '',
                    record.status || '',
                    record.scheduledAt || '',
                    record.completedAt || '',
                    record.technician || '',
                    record.inspector || '',
                    record.result || '',
                    record.roadworthy || '',
                    record.totalCost ?? 0
                ])),
                filename: `${slug || 'maintenance_log'}.csv`
            };
        }

        return {
            headers: withPeriod(['Status']),
            rows: [],
            filename: `${slug || 'report'}.csv`
        };
    }

function exportReportBuilderCsv() {
        const reportType = getSelectedReportType();
        const payload = getReportBuilderCsvPayload(reportType);

        if (!payload.rows || payload.rows.length === 0) {
            GoPasigUI.alert('No CSV records available for this report type and selected period.');
            return false;
        }

        const exported = downloadAnalyticsCSV(payload.headers, payload.rows, payload.filename);
        if (exported) {
            GoPasigUI.alert('CSV export generated from the current report preview.');
        }

        return exported;
    }

function updateReportLivePreview() {
        const reportType = getSelectedReportType();
        const typeLabel = getReportTypeLabel(reportType);
        const period = window.analyticsReportingPeriod || {
            label: 'Today'
        };
        const metrics = getAnalyticsPreviewMetricSet(reportType);

        const setText = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setText('preview-report-title', `${typeLabel} · ${period.label}`);
        setText('preview-period-label', period.label);
        setText('preview-metric-a-label', metrics.aLabel);
        setText('preview-metric-a', metrics.aValue);
        setText('preview-metric-b-label', metrics.bLabel);
        setText('preview-metric-b', metrics.bValue);
        setText('preview-metric-c-label', metrics.cLabel);
        setText('preview-metric-c', metrics.cValue);

        document.querySelectorAll('[data-preview-export-button]').forEach(btn => {
            const payload = getReportBuilderCsvPayload(reportType);
            const canExport = payload.rows.length > 0;
            btn.disabled = true;
            btn.onclick = null;
            btn.className = canExport
                ? 'rounded border border-[#003F87]/20 bg-white px-3 py-1 text-[10px] font-extrabold text-[#003F87] shadow-sm hover:bg-[#E6F1FB] cursor-pointer'
                : 'rounded bg-slate-100 px-3 py-1 text-[10px] font-extrabold text-slate-400 cursor-not-allowed';
            btn.textContent = canExport
                ? 'Export CSV'
                : 'No data to export';
            btn.title = canExport
                ? 'Export this report preview as CSV.'
                : 'No current analytics payload rows are available for this report type and period.';
            if (canExport) {
                btn.disabled = false;
                btn.onclick = exportReportBuilderCsv;
            }
        });
    }

function syncAnalyticsPeriodControls() {
        const period = window.analyticsReportingPeriod || {
            preset: 'today',
            label: 'Today',
            start: formatAnalyticsDate(new Date()),
            end: formatAnalyticsDate(new Date())
        };

        document.querySelectorAll('.analytics-period-summary, .analytics-period-label').forEach(el => {
            el.textContent = period.label;
        });

        document.querySelectorAll('[data-analytics-period-preset]').forEach(btn => {
            const active = btn.dataset.analyticsPeriodPreset === period.preset;
            btn.className = active
                ? 'rounded-lg border border-[#003F87] bg-[#E6F1FB] px-2.5 py-1 text-[10px] font-extrabold text-[#003F87] transition'
                : 'rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 transition hover:bg-slate-50';
        });

        document.querySelectorAll('.analytics-custom-range').forEach(group => {
            if (period.preset === 'custom') {
                group.classList.remove('hidden');
                group.classList.add('flex');
            } else {
                group.classList.add('hidden');
                group.classList.remove('flex');
            }
        });

        document.querySelectorAll('[data-analytics-start-date]').forEach(input => {
            input.value = period.start || '';
        });

        document.querySelectorAll('[data-analytics-end-date]').forEach(input => {
            input.value = period.end || '';
        });

        updateReportLivePreview();
    }

async function applyAnalyticsReportingPeriod(period) {
        if (typeof setAnalyticsReportingPeriod === 'function') {
            setAnalyticsReportingPeriod(period);
        } else {
            window.analyticsReportingPeriod = period;
        }

        syncAnalyticsPeriodControls();

        if (typeof loadDatabaseAnalyticsData === 'function') {
            // loadDatabaseAnalyticsData() owns fetch + applyAnalyticsPayload(data) to keep one analytics mutation path.
            await loadDatabaseAnalyticsData();
        }
    }

function callReportBuilderGenerate(reportType, periodLabel, hasData) {
        const button = document.getElementById('generate-report-btn');
        const componentRoot = button?.closest('[wire\\:id]');
        const componentId = componentRoot?.getAttribute('wire:id');

        if (!componentId || !window.Livewire || typeof window.Livewire.find !== 'function') {
            return Promise.resolve(false);
        }

        const component = window.Livewire.find(componentId);
        if (!component || typeof component.call !== 'function') {
            return Promise.resolve(false);
        }

        return component.call('generateReport', {
            reportType,
            periodLabel,
            hasData
        }).then(() => true);
    }

// 5A & 5B: interactive forms handlers & log history update
    function handleGenerateReport(event) {
        event.preventDefault();

        const btn = document.getElementById('generate-report-btn');
        const btnText = document.getElementById('report-btn-text');
        
        if (!btn || !btnText) return;

        // Save the live preview metadata from the current analytics payload and shared reporting period.
        btn.disabled = true;
        btnText.textContent = 'Saving...';
        btn.classList.add('opacity-75');

        setTimeout(async () => {
            updateReportLivePreview();
            const reportType = getSelectedReportType();
            const period = window.analyticsReportingPeriod || { label: 'Today' };
            const metrics = getAnalyticsPreviewMetricSet(reportType);
            const saved = await callReportBuilderGenerate(reportType, period.label, metrics.hasData);

            // Reset button state
            btn.disabled = false;
            btnText.textContent = 'Generate Report Record';
            btn.classList.remove('opacity-75');

            GoPasigUI.alert(saved
                ? 'Report preview saved to export history. Use Export CSV when this report has rows.'
                : 'Document live preview refreshed, but export history could not be updated.');
        }, 300);
    }

    // Setup segmented button controls and pill selection checkmarks at start
    function bindAnalyticsInteractiveEvents() {
        document.querySelectorAll('[data-analytics-period-preset]').forEach(btn => {
            if (btn.dataset.analyticsPeriodBound === 'true') return;
            btn.dataset.analyticsPeriodBound = 'true';
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const preset = this.dataset.analyticsPeriodPreset;
                if (preset === 'custom') {
                    const current = window.analyticsReportingPeriod || getAnalyticsPresetRange('today');
                    await applyAnalyticsReportingPeriod({
                        preset: 'custom',
                        label: 'Custom Range',
                        start: current.start,
                        end: current.end
                    });
                    return;
                }

                await applyAnalyticsReportingPeriod(getAnalyticsPresetRange(preset));
            });
        });

        document.querySelectorAll('[data-analytics-custom-apply]').forEach(btn => {
            if (btn.dataset.analyticsCustomPeriodBound === 'true') return;
            btn.dataset.analyticsCustomPeriodBound = 'true';
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const wrapper = this.closest('.analytics-reporting-period-control');
                const start = wrapper?.querySelector('[data-analytics-start-date]')?.value;
                const end = wrapper?.querySelector('[data-analytics-end-date]')?.value;

                if (!start || !end) {
                    GoPasigUI.alert('Select both start and end dates for the custom reporting period.');
                    return;
                }

                if (start > end) {
                    GoPasigUI.alert('Start date cannot be later than end date.');
                    return;
                }

                await applyAnalyticsReportingPeriod({
                    preset: 'custom',
                    label: `${start} to ${end}`,
                    start,
                    end
                });
            });
        });

        // Report type radio selectors hover / checkmarks styling highlights
        document.querySelectorAll('input[name="report_type"]').forEach(radio => {
            if (radio.dataset.analyticsReportTypeBound === 'true') return;
            radio.dataset.analyticsReportTypeBound = 'true';
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

                updateReportLivePreview();
            });
        });

        // Pill checkboxes active background color toggle highlights
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            const label = checkbox.closest('label');
            if (label && label.textContent.includes('Route')) {
                if (checkbox.dataset.analyticsRoutePillBound === 'true') return;
                checkbox.dataset.analyticsRoutePillBound = 'true';
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
        if (reportForm && reportForm.dataset.analyticsReportFormBound !== 'true') {
            reportForm.dataset.analyticsReportFormBound = 'true';
            reportForm.addEventListener('submit', handleGenerateReport);
        }
        const reportRefreshBtn = document.getElementById('generate-report-btn');
        if (reportRefreshBtn && reportRefreshBtn.dataset.analyticsReportRefreshBound !== 'true') {
            reportRefreshBtn.dataset.analyticsReportRefreshBound = 'true';
            reportRefreshBtn.addEventListener('click', handleGenerateReport);
        }

        updateLayoutExportButton();
        syncAnalyticsPeriodControls();
    }

    window.bindAnalyticsInteractiveEvents = bindAnalyticsInteractiveEvents;
    window.syncAnalyticsPeriodControls = syncAnalyticsPeriodControls;
    window.updateReportLivePreview = updateReportLivePreview;
    window.exportReportBuilderCsv = exportReportBuilderCsv;

    document.addEventListener('livewire:init', () => {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;

        window.Livewire.hook('morph.updated', () => {
            window.requestAnimationFrame(() => {
                bindAnalyticsInteractiveEvents();
                syncAnalyticsPeriodControls();
            });
        });
    });

