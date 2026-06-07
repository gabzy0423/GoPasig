{{-- ==================== BUS MANAGEMENT SCREEN ==================== --}}
<section id="screen-buses" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    {{-- PAGE HEADER ROW --}}
    <div class="bm-page-header">
        <div class="bm-page-header-left">
            <h1 class="bm-h1">Bus management</h1>
            <p class="bm-subtitle" id="bm-buses-registered-label">6 registered municipal buses · Pasig Libreng Sakay Fleet</p>
        </div>
        <div class="bm-page-header-right">
            <button class="bm-btn-primary" onclick="openAddBusModal('add')">
                <i class="ti ti-plus"></i> Add bus registration
            </button>
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
            <button data-bus-filter="Active" class="bm-filter-btn" onclick="filterBuses('Active')">Active</button>
            <button data-bus-filter="Inactive" class="bm-filter-btn" onclick="filterBuses('Inactive')">Inactive</button>
            <button data-bus-filter="Maintenance" class="bm-filter-btn" onclick="filterBuses('Maintenance')">Maintenance</button>
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

    .bm-btn-outline {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 28px;
        padding: 0 10px;
        background: transparent;
        color: var(--color-text-primary);
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.12s;
    }

    .bm-btn-outline:hover {
        background: var(--color-background-secondary);
    }

    .bm-btn-danger-text {
        color: #A32D2D;
        border-color: #FCEBEB;
    }

    .bm-btn-danger-text:hover {
        background: #FCEBEB;
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

    .bm-route-1 { background: #E6F1FB; color: #0C447C; }
    .bm-route-2 { background: #EAF3DE; color: #3B6D11; }
    .bm-route-3 { background: #FAEEDA; color: #854F0B; }
    .bm-route-4 { background: #FCEBEB; color: #A32D2D; }
    .bm-route-none { background: #F1EFE8; color: #5F5E5A; }
</style>
