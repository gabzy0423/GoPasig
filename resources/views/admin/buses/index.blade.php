<section id="screen-buses" class="hidden space-y-6"
    style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- LIST CONTAINER -->
    <div id="buses-list-container" class="space-y-6">
        {{-- PAGE HEADER ROW --}}
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Bus Management</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span id="buses-breadcrumb-current" class="text-slate-600 font-bold">Bus Management</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-semibold mt-1" id="bm-buses-registered-label">6 registered
                        municipal buses · Pasig Libreng Sakay Fleet</p>
                </div>
                <div>
                    <button onclick="openAddBusModal('add'); return false;"
                        class="bm-btn-primary flex items-center gap-2 border-none">
                        <i class="ti ti-plus"></i> Add bus registration
                    </button>
                </div>
            </div>
        </div>

        {{-- STATS STRIP --}}
        <div class="bm-stats-strip">
            <div class="bm-stat-card">
                <span class="bm-stat-label">Total Fleet</span>
                <span class="bm-stat-value text-slate-800" id="bm-stat-total">6</span>
            </div>
            <div class="bm-stat-card">
                <span class="bm-stat-label">Active / On Road</span>
                <span class="bm-stat-value text-[#639922]" id="bm-stat-active">5</span>
            </div>
            <div class="bm-stat-card">
                <span class="bm-stat-label">Inactive / Idle</span>
                <span class="bm-stat-value text-slate-500" id="bm-stat-inactive">0</span>
            </div>
            <div class="bm-stat-card">
                <span class="bm-stat-label">Maintenance</span>
                <span class="bm-stat-value text-[#BA7517]" id="bm-stat-maintenance">1</span>
            </div>
        </div>

        {{-- FILTER BAR --}}
        <div class="bm-filter-bar">
            <div class="bm-search-wrapper">
                <i class="ti ti-search bm-search-icon"></i>
                <input id="bus-search" type="text" class="bm-search-input" placeholder="Search plate number…"
                    oninput="searchBusesTable()">
            </div>
            <div class="flex gap-2">
                <button data-bus-filter="all" class="bm-filter-btn active" onclick="filterBuses('all')">All</button>
                <button data-bus-filter="active" class="bm-filter-btn" onclick="filterBuses('active')">Active</button>
                <button data-bus-filter="inactive" class="bm-filter-btn"
                    onclick="filterBuses('inactive')">Inactive</button>
                <button data-bus-filter="maintenance" class="bm-filter-btn"
                    onclick="filterBuses('maintenance')">Maintenance</button>
            </div>
            <span id="bm-showing-count" class="bm-count-label">Showing 6 of 6 buses</span>
        </div>

        {{-- MAIN TABLE CARD --}}
        <div class="bm-table-card">
            <div class="overflow-x-auto w-full">
                <table class="bm-table">
                    <thead>
                        <tr class="bm-thead-row">
                            <th class="bm-th">Plate Number</th>
                            <th class="bm-th">Assigned Route</th>
                            <th class="bm-th">Assigned Driver</th>
                            <th class="bm-th" style="text-align:center;">Capacity</th>
                            <th class="bm-th" style="text-align:center;">Pax Boarded</th>
                            <th class="bm-th" style="text-align:center;">Speed</th>
                            <th class="bm-th">Next Stop</th>
                            <th class="bm-th">Status</th>
                            <th class="bm-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="buses-tbody">
                        {{-- Populated dynamically by buses.js --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FORM CONTAINER -->
    <div id="buses-form-container" class="hidden space-y-6">
        <!-- BREADCRUMB & HEADER -->
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="closeAddBusModal(); return false;"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none"
                    title="Back to Bus Management">
                    <i class="ti ti-arrow-left text-lg"></i>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight" id="add-bus-modal-title">Register
                        Municipal Bus</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Bus Management</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="text-[#003F87] font-bold" id="buses-breadcrumb-current-sub">Register Bus</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
            <div class="mb-6">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1" id="add-bus-modal-title-sec">
                    Bus Unit Specifications</h2>
                <p class="text-xs text-slate-500" id="add-bus-modal-desc">Provide the plate number, driver, route,
                    capacity, and current operational status to register a new bus.</p>
            </div>

            <form id="add-bus-form" onsubmit="handleBusSubmit(event)" class="space-y-6">
                <input type="hidden" id="edit-bus-id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Bus Plate No -->
                    <div class="space-y-2">
                        <label for="new-bus-plate" class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus
                            Plate No</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-id text-base"></i>
                            </span>
                            <input id="new-bus-plate" name="plate_number" type="text" placeholder="e.g. PAS-439"
                                required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Unique plate number or designation code for
                            the bus.</p>
                    </div>

                    <!-- Seating Capacity -->
                    <div class="space-y-2">
                        <label for="new-bus-capacity"
                            class="text-xs font-bold uppercase tracking-wider text-slate-500">Seating Capacity</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-users text-base"></i>
                            </span>
                            <input id="new-bus-capacity" name="capacity" type="number" placeholder="e.g. 45" value="45"
                                min="{{ \App\Models\SystemSetting::get('bus_capacity_min', 10) }}" max="{{ \App\Models\SystemSetting::get('bus_capacity_max', 150) }}" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Passenger limit of this bus unit (minimum 10,
                            maximum 100).</p>
                    </div>

                    <!-- Driver Assignment -->
                    <div class="space-y-2">
                        <label for="new-bus-driver"
                            class="text-xs font-bold uppercase tracking-wider text-slate-500">Driver Assignment</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-steering-wheel text-base"></i>
                            </span>
                            <input id="new-bus-driver" name="driver_name" type="text" placeholder="e.g. Cardo Dalisay"
                                required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Name of the primary operator driving this
                            unit.</p>
                    </div>

                    <!-- Route Assignment -->
                    <div class="space-y-2">
                        <label for="new-bus-route"
                            class="text-xs font-bold uppercase tracking-wider text-slate-500">Route Assignment</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-route text-base"></i>
                            </span>
                            <select id="new-bus-route" name="route_id" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                <option value="{{ \App\Models\Bus::DEFAULT_NEXT_STOP }}">{{ \App\Models\Bus::DEFAULT_NEXT_STOP }} - Unassigned</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route->id }}">{{ $route->name }} - {{ $route->description }}</option>
                                @endforeach
                            </select>
                            <span
                                class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                <i class="ti ti-chevron-down text-sm"></i>
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">The municipal transit line to dispatch this
                            unit to.</p>
                    </div>

                    <!-- Status -->
                    <div class="space-y-2 md:col-span-2">
                        <label for="new-bus-status"
                            class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-activity text-base"></i>
                            </span>
                            <select id="new-bus-status" name="status" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                <option value="active">Active (On road / dispatch-ready)</option>
                                <option value="inactive">Inactive (Idle / off-duty)</option>
                                <option value="maintenance">Maintenance (Undergoing repairs)</option>
                            </select>
                            <span
                                class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                <i class="ti ti-chevron-down text-sm"></i>
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Current operational status determining its
                            visibility in commuter apps.</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                    <button type="button" onclick="closeAddBusModal(); return false;"
                        class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="bus-submit-btn"
                        class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        Register Bus
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

{{-- ==================== SCOPED CSS FOR BUS MANAGEMENT ==================== --}}
<style>
    #screen-buses {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    /* ── Page Header ── */
    .bm-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .bm-h1 {
        font-size: 20px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
        line-height: 1.3;
    }

    .bm-subtitle {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin: 2px 0 0;
    }

    .bm-page-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── Buttons ── */
    .bm-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 36px;
        padding: 0 14px;
        background: #003F87;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
        white-space: nowrap;
    }

    .bm-btn-primary:hover {
        background: #002d62;
    }

    .bm-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
        color: var(--color-text-secondary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .bm-icon-btn:hover {
        background: var(--color-background-secondary);
        color: #003F87;
        border-color: #003F87;
        transform: translateY(-1px);
    }

    .bm-icon-btn--danger:hover {
        background: #FCEBEB;
        color: #A32D2D;
        border-color: #F09595;
    }

    /* ── Stats Strip ── */
    .bm-stats-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .bm-stat-card {
        display: flex;
        flex-direction: column;
        gap: 4px;
        background: var(--color-background-secondary);
        border-radius: 8px;
        padding: 14px 16px;
    }

    .bm-stat-label {
        font-size: 12px;
        color: var(--color-text-secondary);
        font-weight: 500;
    }

    .bm-stat-value {
        font-size: 22px;
        font-weight: 500;
        line-height: 1.2;
    }

    /* ── Filter Bar ── */
    .bm-filter-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .bm-search-wrapper {
        position: relative;
        width: 220px;
        flex-shrink: 0;
    }

    .bm-search-icon {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 14px;
        pointer-events: none;
    }

    .bm-search-input {
        width: 100%;
        height: 34px;
        padding: 0 10px 0 30px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 12px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        transition: border-color 0.15s;
        box-sizing: border-box;
    }

    .bm-search-input:focus {
        border-color: #003F87;
    }

    .bm-filter-btn {
        height: 34px;
        padding: 0 12px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-secondary);
        background: var(--color-background-secondary);
        color: var(--color-text-secondary);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }

    .bm-filter-btn.active {
        background: #003F87;
        color: #ffffff;
        border-color: #003F87;
    }

    .bm-count-label {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-left: auto;
    }

    /* ── Table Card ── */
    .bm-table-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 12px;
        overflow: hidden;
    }

    .bm-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bm-thead-row {
        background: var(--color-background-secondary);
    }

    .bm-th {
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        text-align: left;
        white-space: nowrap;
    }

    .bm-td {
        padding: 11px 14px;
        font-size: 13px;
        color: var(--color-text-primary);
        vertical-align: middle;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .bm-tbody-row {
        transition: background 0.1s;
    }

    .bm-tbody-row:hover {
        background: #EEF3FF;
    }

    .bm-tbody-row:last-child .bm-td {
        border-bottom: none;
    }

    /* Custom Badges */
    .bm-route-pill {
        display: inline-block;
        padding: 2.5px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 600;
    }

    .bm-route-1 {
        background: #E6F1FB;
        color: #0C447C;
    }

    .bm-route-2 {
        background: #EAF3DE;
        color: #3B6D11;
    }

    .bm-route-3 {
        background: #FAEEDA;
        color: #854F0B;
    }

    .bm-route-4 {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .bm-route-none {
        background: #F1EFE8;
        color: #5F5E5A;
    }

    /* Transitions & Animations */
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