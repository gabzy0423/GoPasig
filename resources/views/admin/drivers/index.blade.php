{{-- ==================== DRIVER MANAGEMENT SCREEN ==================== --}}
<section id="screen-drivers" class="hidden"
  style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

  {{-- PAGE HEADER ROW --}}
  <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
              <h1 class="text-xl font-bold text-slate-900">Driver Management</h1>
              <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                  <span>Dashboard</span>
                  <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                  <span>Fleet</span>
                  <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                  <span class="text-slate-600 font-bold">Driver Management</span>
              </div>
              <p id="dm-registered-drivers-subtitle" class="text-[11px] text-slate-500 font-semibold mt-1">0 registered drivers · Pasig City Libreng Sakay Program</p>
          </div>
          <div class="flex items-center gap-2">
              <button class="dm-btn-outline" onclick="exportDriversCSV()">
                  <i class="ti ti-download"></i> Export CSV
              </button>
              <button onclick="openDriversCreateScreen(); switchScreen('drivers-create'); return false;" class="dm-btn-primary" style="display: inline-flex; align-items: center; justify-content: center;">
                  <i class="ti ti-user-plus"></i> Add driver
              </button>
          </div>
      </div>
  </div>

  <!-- Primary Status Cards (4 Columns) -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
      <!-- Card 1: Driving -->
      <div onclick="toggleDriverCardFilter('driving', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#639922]" data-driver-card-filter="driving">
          <div class="flex justify-between items-start">
              <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Driving</span>
              <div class="h-6 w-6 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                  <i class="ti ti-steering-wheel text-sm"></i>
              </div>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
              <span class="text-[20px] font-black text-slate-900 leading-none" id="dm-stat-on-duty">0</span>
              <span class="text-[9px] text-slate-500 font-semibold truncate">Currently Driving</span>
          </div>
      </div>

      <!-- Card 2: Available Drivers -->
      <div onclick="toggleDriverCardFilter('available', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#003F87]" data-driver-card-filter="available">
          <div class="flex justify-between items-start">
              <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Available Drivers</span>
              <div class="h-6 w-6 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                  <i class="ti ti-user text-sm"></i>
              </div>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
              <span class="text-[20px] font-black text-slate-900 leading-none" id="dm-stat-standby">0</span>
              <span class="text-[9px] text-slate-500 font-semibold truncate">Available for Dispatch</span>
          </div>
      </div>

      <!-- Card 3: Suspended -->
      <div onclick="toggleDriverCardFilter('suspended', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#E24B4A]" data-driver-card-filter="suspended">
          <div class="flex justify-between items-start">
              <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Suspended</span>
              <div class="h-6 w-6 rounded bg-rose-50 flex items-center justify-center text-[#E24B4A]">
                  <i class="ti ti-ban text-sm"></i>
              </div>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
              <span class="text-[20px] font-black text-slate-900 leading-none" id="dm-stat-suspended">0</span>
              <span class="text-[9px] text-slate-500 font-semibold truncate">Unavailable for Assignment</span>
          </div>
      </div>

      <!-- Card 4: License Attention (Non-clickable) -->
      <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm border-l-[3px] border-l-[#BA7517]" data-driver-card-filter="attention">
          <div class="flex justify-between items-start">
              <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">License Attention</span>
              <div class="h-6 w-6 rounded bg-amber-50 flex items-center justify-center text-[#BA7517]">
                  <i class="ti ti-license text-sm"></i>
              </div>
          </div>
          <div class="mt-1 flex items-baseline gap-1.5">
              <span class="text-[20px] font-black text-slate-900 leading-none" id="dm-stat-attention">0</span>
              <span class="text-[9px] text-slate-500 font-semibold truncate">Expiring / Expired</span>
          </div>
      </div>
  </div>

  <!-- Secondary Driver Health Indicators section -->
  <div class="space-y-2 mb-6">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 select-none">Driver Health Indicators</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
          <!-- Assigned Drivers -->
          <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#639922]">
              <div class="flex justify-between items-center">
                  <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Assigned Drivers</span>
                  <div class="h-5 w-5 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                      <i class="ti ti-circle-check text-xs"></i>
                  </div>
              </div>
              <div class="flex items-baseline gap-1.5">
                  <span class="text-[16px] font-black text-slate-800 leading-none" id="dm-health-assigned">0</span>
                  <span class="text-[9px] text-slate-450 font-medium truncate">Active assignments</span>
              </div>
          </div>

          <!-- Completed Trips Today -->
          <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#003F87]">
              <div class="flex justify-between items-center">
                  <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Completed Trips Today</span>
                  <div class="h-5 w-5 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                      <i class="ti ti-trophy text-xs"></i>
                  </div>
              </div>
              <div class="flex items-baseline gap-1.5">
                  <span class="text-[16px] font-black text-slate-800 leading-none" id="dm-health-completed-today">0</span>
                  <span class="text-[9px] text-slate-450 font-medium truncate">Actual completed Trips</span>
              </div>
          </div>

          <!-- License Expired -->
          <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#E24B4A]">
              <div class="flex justify-between items-center">
                  <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">License Expired</span>
                  <div class="h-5 w-5 rounded bg-rose-50 flex items-center justify-center text-[#E24B4A]">
                      <i class="ti ti-alert-triangle text-xs"></i>
                  </div>
              </div>
              <div class="flex items-baseline gap-1.5">
                  <span class="text-[16px] font-black text-slate-800 leading-none" id="dm-health-expired">0</span>
                  <span class="text-[9px] text-slate-450 font-medium truncate">Must renew status</span>
              </div>
          </div>

          <!-- No Active Trips -->
          <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-slate-400">
              <div class="flex justify-between items-center">
                  <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">No Active Trips</span>
                  <div class="h-5 w-5 rounded bg-slate-200 flex items-center justify-center text-slate-650">
                      <i class="ti ti-activity-heartbeat text-xs"></i>
                  </div>
              </div>
              <div class="flex items-baseline gap-1.5">
                  <span class="text-[16px] font-black text-slate-800 leading-none" id="dm-health-no-trips">0</span>
                  <span class="text-[9px] text-slate-450 font-medium truncate">No dispatched / ongoing Trip</span>
              </div>
          </div>
      </div>
  </div>

  {{-- STANDARD TOOLBAR --}}
  <div class="flex flex-col md:flex-row md:items-center gap-4 w-full bg-white p-4 border border-slate-200 rounded-xl shadow-sm select-none mb-6">
      <!-- Left Region: Search input (only flexible element) -->
      <div class="relative flex-1 min-w-0">
          <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
          <input id="driver-search" type="text" 
                 class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-800 focus:outline-none focus:border-[#003F87] focus:ring-1 focus:ring-[#003F87] transition-all" 
                 placeholder="Search driver, employee ID, or license number..."
                 oninput="filterDriversTable()">
      </div>
      
      <!-- Middle Region: Filters -->
      <div class="flex flex-wrap items-center gap-3">
          <select id="driver-status-filter" onchange="filterDriversTable()"
              class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
              <option value="">All Statuses</option>
              <option value="driving">Driving</option>
              <option value="assigned">Assigned</option>
              <option value="available">Available</option>
              <option value="unavailable">Unavailable</option>
              <option value="off-duty">Off Duty</option>
              <option value="suspended">Suspended</option>
          </select>

          <select id="driver-license-filter" onchange="filterDriversTable()"
              class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
              <option value="">All License Statuses</option>
              <option value="ok">Valid</option>
              <option value="warn">Expiring Soon</option>
              <option value="expired">Expired</option>
          </select>
      </div>

      <!-- Right Region: Last updated, Refresh, Export -->
      <div class="flex items-center gap-3 whitespace-nowrap shrink-0 text-xs font-semibold text-slate-500">
          <div class="flex items-center gap-1 text-slate-400 select-none px-1">
              <span>Last updated: <span id="dm-last-updated" class="font-mono text-slate-655 font-bold">Just now</span></span>
          </div>
      </div>

      <div class="flex items-center gap-2 shrink-0">
          <button onclick="loadDatabaseDriversData(); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
              <i class="ti ti-refresh text-slate-550"></i> Refresh
          </button>
          <button onclick="exportDriversCSV(); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
              <i class="ti ti-download text-slate-550"></i> Export CSV
          </button>
      </div>
  </div>

  <!-- Table Title and Showing Counter -->
  <div class="flex items-center justify-between mb-3 select-none">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Driver Registry Listing</h3>
      <span id="driver-showing-count" class="text-xs font-semibold text-slate-500">Showing 0 of 0 drivers</span>
  </div>

  {{-- MAIN TABLE CARD --}}
  <div class="dm-table-card">
    <div class="overflow-x-auto w-full">
      <table class="dm-table">
        <colgroup>
          <col style="width:22%">
          <col style="width:21%">
          <col style="width:21%">
          <col style="width:13%">
          <col style="width:16%">
          <col style="width:7%">
        </colgroup>
        <thead>
          <tr class="dm-thead-row">
            <th class="dm-th">Driver</th>
            <th class="dm-th">License</th>
            <th class="dm-th">Current assignment</th>
            <th class="dm-th">Status</th>
            <th class="dm-th">Today's operations</th>
            <th class="dm-th" style="text-align: right; padding-right: 16px;">Actions</th>
          </tr>
        </thead>
        <tbody id="drivers-tbody">
          {{-- Rows populated by drivers.js renderDriversTable() --}}
        </tbody>
      </table>
    </div>

    {{-- PAGINATION ROW --}}
    <div class="dm-pagination-row">
      <span class="dm-count-label">1–8 of 34 drivers</span>
      <div class="dm-page-btns">
        <button class="dm-page-btn dm-page-btn--active">1</button>
        <button class="dm-page-btn">2</button>
        <button class="dm-page-btn">3</button>
        <button class="dm-page-btn">›</button>
      </div>
    </div>
  </div>



</section>

{{-- ==================== SCOPED CSS ==================== --}}
<style>
  /* ── Design tokens (local overrides already in inline style on section) ── */
  #screen-drivers,
  #driver-profile-drawer,
  #driver-modal {
    --color-background-primary: #ffffff;
    --color-background-secondary: #F8F7F4;
    --color-text-primary: #1A1917;
    --color-text-secondary: #5F5E5A;
    --color-border-tertiary: #E8E6DF;
    --color-border-secondary: #D6D3C9;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  }

  /* ── Page header ── */
  .dm-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }

  .dm-h1 {
    font-size: 20px;
    font-weight: 500;
    color: var(--color-text-primary);
    margin: 0;
    line-height: 1.3;
  }

  .dm-subtitle {
    font-size: 13px;
    color: var(--color-text-secondary);
    margin: 2px 0 0;
  }

  .dm-page-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  /* ── Buttons ── */
  .dm-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 14px;
    background: #003F87;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
  }

  .dm-btn-primary:hover {
    background: #002D62;
  }

  .dm-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 36px;
    padding: 0 14px;
    background: transparent;
    color: var(--color-text-primary);
    border: 0.5px solid var(--color-border-secondary);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
  }

  .dm-btn-outline:hover {
    background: var(--color-background-secondary);
  }

  .dm-btn-sm {
    height: 32px;
    padding: 0 10px;
    font-size: 12px;
  }

  .dm-btn-danger {
    color: #A32D2D;
    border-color: #F09595;
  }

  .dm-btn-danger:hover {
    background: #FCEBEB;
  }

  /* ── Stats strip ── */
  .dm-stats-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
  }

  .dm-stat-card {
    display: flex;
    flex-direction: column;
    gap: 4px;
    background: var(--color-background-secondary);
    border-radius: 8px;
    padding: 14px 16px;
  }

  .dm-stat-label {
    font-size: 12px;
    color: var(--color-text-secondary);
    font-weight: 500;
  }

  .dm-stat-value {
    font-size: 22px;
    font-weight: 500;
    line-height: 1.2;
  }

  /* ── Filter bar ── */
  .dm-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }

  .dm-search-wrapper {
    position: relative;
    width: 220px;
    flex-shrink: 0;
  }

  .dm-search-icon {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-secondary);
    font-size: 14px;
    pointer-events: none;
  }

  .dm-search-input {
    width: 100%;
    height: 36px;
    padding: 0 10px 0 30px;
    border: 0.5px solid var(--color-border-secondary);
    border-radius: 8px;
    font-size: 13px;
    color: var(--color-text-primary);
    background: var(--color-background-primary);
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }

  .dm-search-input:focus {
    border-color: #003F87;
  }

  .dm-select {
    height: 36px;
    padding: 0 10px;
    border: 0.5px solid var(--color-border-secondary);
    border-radius: 8px;
    font-size: 13px;
    color: var(--color-text-primary);
    background: var(--color-background-primary);
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s;
  }

  .dm-select:focus {
    border-color: #003F87;
  }

  .dm-count-label {
    font-size: 12px;
    color: var(--color-text-secondary);
    margin-left: auto;
  }

  /* ── Table card ── */
  .dm-table-card {
    background: var(--color-background-primary);
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: 12px;
    overflow: hidden;
  }

  .dm-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }

  .dm-thead-row {
    background: var(--color-background-secondary);
  }

  .dm-th {
    padding: 10px 12px;
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 0.5px solid var(--color-border-tertiary);
    text-align: left;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--color-background-secondary);
  }

  /* Data rows */
  .dm-tbody-row {
    border-bottom: 0.5px solid var(--color-border-tertiary);
    transition: background 0.1s;
    cursor: default;
  }

  .dm-tbody-row:last-child {
    border-bottom: none;
  }

  .dm-tbody-row:hover {
    background: #EEF3FF;
  }

  .dm-tbody-row.dm-row-expired {
    border-left: 4px solid #E24B4A;
  }

  .dm-td {
    padding: 11px 12px;
    font-size: 13px;
    color: var(--color-text-primary);
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Driver cell */
  .dm-driver-cell {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .dm-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #E6F1FB;
    color: #0C447C;
    font-size: 11px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: 0.02em;
  }

  .dm-driver-name {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .dm-license-cell,
  .dm-assignment-cell {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    min-width: 0;
  }

  .dm-license-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    min-width: 0;
    font-size: 10px;
    color: #64748B;
  }

  .dm-assignment-cell {
    gap: 5px;
  }

  .dm-assignment-empty {
    color: #94A3B8;
    font-size: 12px;
    font-style: italic;
  }

  .dm-operations-cell {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    max-width: 150px;
  }

  .dm-operation-metric {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .dm-operation-value {
    color: #0F172A;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
  }

  .dm-operation-label {
    color: #94A3B8;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.2;
    text-transform: uppercase;
  }

  .dm-driver-empid {
    font-size: 11px;
    font-family: 'Courier New', Courier, monospace;
    color: var(--color-text-secondary);
    margin-top: 1px;
  }

  /* Mono font fields */
  .dm-mono {
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    letter-spacing: 0.02em;
  }

  /* License expiry */
  .dm-expiry-ok {
    color: #3B6D11;
  }

  .dm-expiry-warn {
    color: #854F0B;
  }

  .dm-expiry-urgent,
  .dm-expiry-expired {
    color: #A32D2D;
  }

  .dm-expiry-expired {
    font-weight: 600;
  }

  /* License badges */
  .dm-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 500;
    line-height: 1.6;
    margin-left: 4px;
    vertical-align: middle;
  }

  .dm-badge-warn {
    background: #FAEEDA;
    color: #854F0B;
  }

  .dm-badge-urgent {
    background: #FCEBEB;
    color: #A32D2D;
  }

  .dm-badge-expired {
    background: #FCEBEB;
    color: #A32D2D;
  }

  /* Status chips */
  .dm-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
  }

  .dm-status-on-duty {
    background: #EAF3DE;
    color: #3B6D11;
  }

  .dm-status-off-duty {
    background: #F1EFE8;
    color: #5F5E5A;
  }

  .dm-status-suspended {
    background: #FCEBEB;
    color: #A32D2D;
  }

  /* Route pills */
  .dm-route-chip {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
  }

  .dm-route-a {
    background: #E6F1FB;
    color: #0C447C;
  }

  .dm-route-b {
    background: #EAF3DE;
    color: #3B6D11;
  }

  .dm-route-c {
    background: #FAEEDA;
    color: #854F0B;
  }

  /* Pax mini bar */
  .dm-pax-cell {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .dm-pax-track {
    width: 50px;
    height: 4px;
    border-radius: 2px;
    background: #E6F1FB;
    flex-shrink: 0;
    overflow: hidden;
  }

  .dm-pax-fill {
    height: 100%;
    border-radius: 2px;
  }

  .dm-pax-count {
    font-size: 12px;
    font-weight: 500;
    color: #003F87;
  }

  .dm-pax-none {
    color: var(--color-text-secondary);
    font-size: 13px;
  }

  /* Icon action buttons */
  .dm-actions-cell {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .dm-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 0.5px solid var(--color-border-tertiary);
    background: var(--color-background-primary);
    color: var(--color-text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
  }

  .dm-icon-btn:hover {
    background: var(--color-background-secondary);
    color: #003F87;
    border-color: #003F87;
    transform: translateY(-1px);
  }

  .dm-icon-btn--ban:hover {
    background: #FCEBEB;
    color: #A32D2D;
    border-color: #F09595;
  }

  .dm-icon-btn--ban.dm-banned {
    color: #A32D2D;
  }

  /* ── Pagination ── */
  .dm-pagination-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-top: 0.5px solid var(--color-border-tertiary);
  }

  .dm-page-btns {
    display: flex;
    gap: 4px;
  }

  .dm-page-btn {
    min-width: 30px;
    height: 30px;
    padding: 0 8px;
    border-radius: 6px;
    border: 0.5px solid var(--color-border-tertiary);
    background: var(--color-background-primary);
    color: var(--color-text-secondary);
    font-size: 12px;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
  }

  .dm-page-btn:hover {
    background: var(--color-background-secondary);
  }

  .dm-page-btn--active {
    background: #003F87;
    color: #fff;
    border-color: #003F87;
  }

  /* ── Profile Drawer ── */
  .dm-drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35);
    z-index: 200;
    animation: dm-fade-in 0.2s ease;
  }

  .dm-drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 480px;
    background: var(--color-background-primary);
    border-left: 0.5px solid var(--color-border-tertiary);
    z-index: 201;
    overflow-y: auto;
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(0.25, 1, 0.5, 1);
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
  }

  .dm-drawer.dm-drawer--open {
    transform: translateX(0);
  }

  .dm-drawer-inner {
    padding: 20px;
  }

  .dm-drawer-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
  }

  .dm-drawer-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }

  .dm-profile-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
    flex-wrap: wrap;
  }

  .dm-profile-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #003F87;
    color: #fff;
    font-size: 18px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: 0.04em;
  }

  .dm-profile-identity {
    flex: 1;
    min-width: 0;
  }

  .dm-profile-name {
    font-size: 17px;
    font-weight: 500;
    color: var(--color-text-primary);
  }

  .dm-profile-meta {
    font-size: 12px;
    font-family: 'Courier New', monospace;
    color: var(--color-text-secondary);
    margin-top: 2px;
  }

  .dm-profile-chips {
    display: flex;
    gap: 6px;
    margin-top: 6px;
    flex-wrap: wrap;
    align-items: center;
  }

  .dm-license-warn-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #FAEEDA;
    color: #854F0B;
    padding: 3px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
  }

  .dm-license-urgent-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #FCEBEB;
    color: #A32D2D;
    padding: 3px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 500;
  }

  .dm-profile-actions {
    display: flex;
    gap: 6px;
    margin-left: auto;
    align-items: flex-start;
  }

  .dm-profile-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
  }

  /* Perf score */
  .dm-perf-section {
    margin-bottom: 16px;
  }

  .dm-perf-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
  }

  .dm-perf-track {
    height: 6px;
    border-radius: 3px;
    background: #E6F1FB;
    overflow: hidden;
  }

  .dm-perf-fill {
    height: 100%;
    background: #003F87;
    border-radius: 3px;
    transition: width 0.4s ease;
  }

  /* Trip history */
  .dm-trip-history-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
  }

  .dm-trip-table-wrap {
    border: 0.5px solid var(--color-border-tertiary);
    border-radius: 8px;
    overflow: hidden;
  }

  .dm-trip-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }

  .dm-trip-table thead tr {
    background: var(--color-background-secondary);
  }

  .dm-trip-table th {
    padding: 8px 10px;
    font-size: 11px;
    font-weight: 500;
    color: var(--color-text-secondary);
    text-align: left;
    border-bottom: 0.5px solid var(--color-border-tertiary);
  }

  .dm-trip-table td {
    padding: 8px 10px;
    border-top: 0.5px solid var(--color-border-tertiary);
  }

  .dm-trip-table tbody tr:nth-child(odd) {
    background: var(--color-background-primary);
  }

  .dm-trip-table tbody tr:nth-child(even) {
    background: var(--color-background-secondary);
  }

  .dm-trip-status-done {
    background: #EAF3DE;
    color: #3B6D11;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
  }

  .dm-trip-status-delay {
    background: #FAEEDA;
    color: #854F0B;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
  }

  .dm-trip-status-incident {
    background: #FCEBEB;
    color: #A32D2D;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 10px;
    font-weight: 500;
    white-space: nowrap;
  }

  /* ── Modal ── */
  .dm-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 300;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 20px;
    overflow-y: auto;
    animation: dm-fade-in 0.18s ease;
  }

  .dm-modal-overlay.hidden {
    display: none;
  }

  .dm-modal-card {
    background: var(--color-background-primary);
    border-radius: 12px;
    border: 0.5px solid var(--color-border-tertiary);
    width: 100%;
    max-width: 560px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
    margin-top: 20px;
  }

  .dm-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 0.5px solid var(--color-border-tertiary);
  }

  .dm-modal-title-text {
    font-size: 15px;
    font-weight: 500;
    color: var(--color-text-primary);
  }

  .dm-modal-body {
    padding: 20px;
  }

  .dm-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 0.5px solid var(--color-border-tertiary);
  }

  .dm-footer-right {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  /* Form */
  .dm-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 14px;
  }

  .dm-field {
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  .dm-field-full {
    grid-column: 1 / -1;
  }

  .dm-label {
    display: block;
    font-size: 12px;
    color: var(--color-text-secondary);
    margin-bottom: 4px;
    font-weight: 500;
  }

  .dm-input {
    width: 100%;
    padding: 8px 10px;
    border: 0.5px solid var(--color-border-secondary);
    border-radius: 8px;
    font-size: 13px;
    color: var(--color-text-primary);
    background: var(--color-background-primary);
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
    height: 36px;
  }

  .dm-input:focus {
    border-color: #003F87;
  }

  .dm-input--error {
    border-color: #A32D2D !important;
  }

  .dm-input-readonly {
    background: var(--color-background-secondary);
    color: var(--color-text-secondary);
    cursor: not-allowed;
  }

  .dm-field-error {
    font-size: 11px;
    color: #A32D2D;
    margin-top: 3px;
  }

  .dm-expiry-warn {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #854F0B;
    background: #FAEEDA;
    border-radius: 6px;
    padding: 5px 8px;
    margin-top: 4px;
  }

  /* Info chip */
  .dm-info-chip {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    background: #E6F1FB;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    color: #0C447C;
    margin-top: 4px;
    line-height: 1.5;
  }

  /* Delete link */
  .dm-delete-link {
    font-size: 12px;
    color: #A32D2D;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    margin-right: auto;
    text-decoration: underline;
  }

  .dm-delete-link:hover {
    color: #7A1E1E;
  }

  /* Keyframes */
  @keyframes dm-fade-in {
    from {
      opacity: 0;
    }

    to {
      opacity: 1;
    }
  }

  /* Responsive */
  @media (max-width: 900px) {
    .dm-stats-strip {
      grid-template-columns: repeat(2, 1fr);
    }

    .dm-profile-stats-row {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 640px) {
    .dm-stats-strip {
      grid-template-columns: 1fr 1fr;
    }

    .dm-drawer {
      width: 100%;
    }

    .dm-form-grid {
      grid-template-columns: 1fr;
    }

    .dm-field-full {
      grid-column: 1;
    }

    .dm-page-header {
      flex-direction: column;
    }

    .dm-filter-bar {
      flex-wrap: wrap;
    }

    .dm-search-wrapper {
      width: 100%;
    }
  }

  /* Floating row dropdown menu actions container */
  .dm-dropdown-menu {
      position: absolute;
      right: 0;
      z-index: 9999;
      width: 190px;
      background-color: #ffffff;
      border: 1px solid #E8E6DF;
      border-radius: 12px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
      padding: 6px 0;
      animation: dropdownFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }
  @keyframes dropdownFadeIn {
      from {
          opacity: 0;
          transform: scale(0.95) translateY(-5px);
      }
      to {
          opacity: 1;
          transform: scale(1) translateY(0);
      }
  }
  .dm-dropdown-item {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      height: 40px;
      padding: 0 16px;
      font-size: 13px;
      font-weight: 600;
      color: #1A1917;
      text-align: left;
      transition: background 0.15s ease;
      text-decoration: none;
      background: transparent;
      border: none;
      cursor: pointer;
  }
  .dm-dropdown-item:hover {
      background-color: #F8F7F4;
  }
  .dm-dropdown-item i {
      font-size: 14px;
  }
  .dm-dropdown-divider {
      height: 1px;
      background-color: #E8E6DF;
      margin: 6px 0;
  }

  /* Actions cell visible overflow style */
  .dm-td-actions {
      position: relative;
      overflow: visible !important;
  }

  /* Actions trigger button classes */
  .dm-action-trigger {
      transition: all 0.2s ease;
  }
  .dm-action-trigger:hover {
      background-color: #F8F7F4;
      border-color: #D6D3C9;
      color: #1A1917;
  }
  .dm-action-trigger:focus,
  .dm-action-trigger.active {
      background-color: #EEF3FF !important;
      border-color: #003F87 !important;
      color: #003F87 !important;
  }
</style>
