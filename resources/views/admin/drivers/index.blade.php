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
              <button onclick="window.location.hash = 'drivers-create'; return false;" class="dm-btn-primary" style="display: inline-flex; align-items: center; justify-content: center;">
                  <i class="ti ti-user-plus"></i> Add driver
              </button>
          </div>
      </div>
  </div>

  {{-- STATS STRIP --}}
  <div class="dm-stats-strip">
    <div class="dm-stat-card">
      <span class="dm-stat-label">On duty</span>
      <span id="dm-stat-on-duty" class="dm-stat-value" style="color:#003F87;">0</span>
    </div>
    <div class="dm-stat-card">
      <span class="dm-stat-label">Off duty</span>
      <span id="dm-stat-off-duty" class="dm-stat-value" style="color:var(--color-text-secondary);">0</span>
    </div>
    <div class="dm-stat-card">
      <span class="dm-stat-label">Suspended</span>
      <span id="dm-stat-suspended" class="dm-stat-value" style="color:#A32D2D;">0</span>
    </div>
    <div class="dm-stat-card">
      <span class="dm-stat-label">License expiring (≤30 days)</span>
      <span id="dm-stat-expiring" class="dm-stat-value" style="color:#854F0B;">0</span>
    </div>
  </div>

  {{-- FILTER BAR --}}
  <div class="dm-filter-bar">
    <div class="dm-search-wrapper">
      <i class="ti ti-search dm-search-icon"></i>
      <input id="driver-search" type="text" class="dm-search-input" placeholder="Search name or license no…"
        oninput="filterDriversTable()">
    </div>
    <select id="driver-status-filter" class="dm-select" onchange="filterDriversTable()">
      <option value="">All statuses</option>
      <option value="On Duty">On Duty</option>
      <option value="Off Duty">Off Duty</option>
      <option value="Suspended">Suspended</option>
    </select>
    <select id="driver-license-filter" class="dm-select" onchange="filterDriversTable()">
      <option value="">All licenses</option>
      <option value="ok">Valid</option>
      <option value="warn">Expiring soon</option>
      <option value="expired">Expired</option>
    </select>
    <span id="driver-showing-count" class="dm-count-label">Showing 8 of 8 drivers</span>
  </div>

  {{-- MAIN TABLE CARD --}}
  <div class="dm-table-card">
    <table class="dm-table">
      <colgroup>
        <col style="width:18%">
        <col style="width:12%">
        <col style="width:12%">
        <col style="width:10%">
        <col style="width:8%">
        <col style="width:9%">
        <col style="width:9%">
        <col style="width:10%">
        <col style="width:12%">
      </colgroup>
      <thead>
        <tr class="dm-thead-row">
          <th class="dm-th">Driver</th>
          <th class="dm-th">License no.</th>
          <th class="dm-th">License expiry</th>
          <th class="dm-th">Assigned bus</th>
          <th class="dm-th">Route</th>
          <th class="dm-th">Status</th>
          <th class="dm-th" style="text-align:center;">Trips today</th>
          <th class="dm-th">Pax today</th>
          <th class="dm-th" style="text-align: right; padding-right: 16px;">Actions</th>
        </tr>
      </thead>
      <tbody id="drivers-tbody">
        {{-- Rows populated by drivers.js renderDriversTable() --}}
      </tbody>
    </table>

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
    background: #FFF5F5;
  }

  .dm-tbody-row.dm-row-expired:hover {
    background: #FDEAEA;
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
</style>