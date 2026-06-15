{{-- ==================== SERVICE ALERT HISTORY SCREEN ==================== --}}
<section id="screen-alerts-history" class="hidden">
    <!-- BREADCRUMB & HEADER -->
    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
        <div class="flex items-center gap-4">
            <a href="#alerts" onclick="switchScreen('alerts'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95" 
               title="Back to Service Alerts">
                <i class="ti ti-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Alert History Log</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Service Alerts</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Alert History</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLE CARD -->
    <div id="history-content" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in">
        
        <!-- Filters and Export CSV -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6 pb-4 border-b border-slate-100">
            <!-- filters -->
            <div class="flex flex-wrap items-center gap-4">
                <!-- Severity -->
                <div class="flex flex-col gap-1">
                    <label for="hist-filter-sev" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Severity</label>
                    <select id="hist-filter-sev" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 px-3 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white" onchange="handleHistoryFilterSeverityChange(event)">
                        <option value="All">All severities</option>
                        <option value="Emergency">Emergency</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low / Info</option>
                    </select>
                </div>
                
                <!-- Type -->
                <div class="flex flex-col gap-1">
                    <label for="hist-filter-type" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Type</label>
                    <select id="hist-filter-type" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 px-3 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white" onchange="handleHistoryFilterTypeChange(event)">
                        <option value="All">All types</option>
                        <option value="Delay">Delay</option>
                        <option value="Route change">Route change</option>
                        <option value="Suspension">Suspension</option>
                        <option value="Breakdown">Breakdown</option>
                        <option value="Weather">Weather</option>
                    </select>
                </div>

                <!-- Route -->
                <div class="flex flex-col gap-1">
                    <label for="hist-filter-route" class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Route</label>
                    <select id="hist-filter-route" class="rounded-lg border border-slate-200 bg-slate-50 py-1.5 px-3 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white" onchange="handleHistoryFilterRouteChange(event)">
                        <option value="All">All routes</option>
                        <option value="Route A">Route A</option>
                        <option value="Route B">Route B</option>
                        <option value="Route C">Route C</option>
                        <option value="All routes">All routes only</option>
                    </select>
                </div>
            </div>

            <!-- Export CSV button -->
            <div class="self-end">
                <button onclick="exportHistoryCSV()" class="rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition flex items-center gap-1.5 cursor-pointer hover:scale-[1.02] active:scale-[0.98]">
                    <i class="ti ti-download text-sm"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Alert History Table -->
        <div class="overflow-x-auto border border-slate-150 rounded-xl">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200">
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">Date & time</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[12%]">Type</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[13%]">Severity</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[25%]">Title</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[15%]">Affects</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[10%]">Sent by</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[10%]">Reached</th>
                        <th class="py-3 px-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 w-[10%]">Status</th>
                    </tr>
                </thead>
                <tbody id="history-table-body" class="divide-y divide-slate-100 text-xs">
                    <tr>
                        <td colspan="8" class="text-center py-12 text-slate-400 font-semibold">
                            Loading alert logs...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between mt-6" id="history-pagination">
            {{-- Loaded by JS --}}
        </div>
    </div>
</section>

<style>
    /* Styling tokens */
    #history-content {
        --color-background-primary: #ffffff;
        --color-background-secondary: #F8F7F4;
        --color-background-tertiary: #F4F3EF;
        --color-text-primary: #1A1917;
        --color-text-secondary: #5F5E5A;
        --color-border-secondary: #D6D3C9;
        --color-border-tertiary: #E8E6DF;
    }

    .am-table-row {
        border-bottom: 0.5px solid var(--color-border-tertiary);
        transition: background-color 0.15s ease;
    }

    .am-table-row:hover {
        background-color: #F8FAFC !important;
    }

    .am-table-cell {
        font-size: 13px;
        color: var(--color-text-primary);
        vertical-align: middle;
    }

    .am-severity-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1.2;
    }

    .badge-emergency { background: #FCEBEB; color: #A32D2D; }
    .badge-high { background: #FAECE7; color: #7A2000; }
    .badge-medium { background: #FAEEDA; color: #854F0B; }
    .badge-low { background: #E6F1FB; color: #0C447C; }

    .am-route-pill-display {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
    }

    .am-route-pill-display.selected-a { background: #E6F1FB; color: #0C447C; }
    .am-route-pill-display.selected-b { background: #EAF3DE; color: #3B6D11; }
    .am-route-pill-display.selected-c { background: #FAEEDA; color: #854F0B; }
    .am-route-pill-display.selected-all { background: #003F87; color: #ffffff; }

    .am-card-route-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .chip-type-display {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        color: var(--color-text-primary);
        font-weight: 500;
    }

    .mono {
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        letter-spacing: 0.02em;
    }

    .font-bold { font-weight: bold; }
    .font-medium { font-weight: 500; }
    .font-blue { color: #003F87; }

    /* History Pagination */
    .am-page-btns {
        display: flex;
        gap: 4px;
    }

    .am-page-btn {
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        border-radius: 6px;
        border: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
        color: var(--color-text-secondary);
        font-size: 12px;
        cursor: pointer;
        transition: all 0.12s;
    }

    .am-page-btn:hover {
        background: var(--color-background-secondary);
        color: #003F87;
    }

    .am-page-btn--active {
        background: #003F87 !important;
        color: #ffffff !important;
        border-color: #003F87 !important;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .animate-fade-in {
        animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
