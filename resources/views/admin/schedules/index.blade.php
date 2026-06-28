{{-- ==================== SCHEDULE & ROUTES MANAGEMENT SCREEN ==================== --}}
<section id="screen-routes" class="hidden"
    style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-background-tertiary:#F4F3EF;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-secondary:#D6D3C9;--color-border-tertiary:#E8E6DF;">

    {{-- PAGE HEADER ROW --}}
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Schedule & Routes</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-slate-600 font-bold">Schedule & Routes</span>
                </div>
                <p class="text-[11px] text-slate-505 font-semibold mt-1">Manage trip timetables, route configurations, and stop sequences</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="switchScreen('schedules-conflict'); return false;" id="btn-conflict-check-header" class="rm-btn-conflict flex items-center justify-center gap-1.5 hover:scale-[1.02] active:scale-[0.98] transition-all border-none">
                    <i class="ti ti-alert-triangle"></i> Conflict check
                </button>
                <button onclick="openCreateScheduleForm(null, null); return false;" class="rm-btn-primary flex items-center justify-center gap-1.5 hover:scale-[1.02] active:scale-[0.98] transition-all border-none">
                    <i class="ti ti-plus"></i> Create schedule
                </button>
            </div>
        </div>
    </div>

    {{-- MAIN TAB ROW --}}
    <div class="rm-tab-row">
        <div class="rm-pill-group">
            <button id="rm-tab-btn-schedule" class="rm-tab-btn active"
                onclick="switchRoutesTab('schedule')">Schedule</button>
            <button id="rm-tab-btn-stops" class="rm-tab-btn" onclick="switchRoutesTab('stops')">Routes & stops</button>
        </div>
    </div>

    {{-- ==================== SCREEN CONTAINER 1: SCHEDULE VIEW ==================== --}}
    <div id="rm-panel-schedule" class="rm-panel-content">
        {{-- SCHEDULE SUB-HEADER --}}
        <div class="rm-sub-header">
            <div class="rm-sub-header-left">
                <div class="rm-date-selector">
                    <button class="rm-icon-btn-square" onclick="adjustDate(-1)"><i
                            class="ti ti-chevron-left"></i></button>
                    <span id="rm-schedule-date-label" class="rm-date-label">{{ now()->format('l, M d, Y') }}</span>
                    <button class="rm-icon-btn-square" onclick="adjustDate(1)"><i
                            class="ti ti-chevron-right"></i></button>
                </div>
                <button class="rm-btn-outline rm-btn-sm active" id="btn-week-view" onclick="toggleWeekView()">
                    <i class="ti ti-calendar-week"></i> Week view
                </button>
            </div>
            <div class="rm-sub-header-right">
                <button class="rm-btn-outline rm-btn-sm" onclick="exportScheduleCSV()">
                    <i class="ti ti-download"></i> Export schedule
                </button>
                <button onclick="openCreateScheduleForm(null, null); return false;" class="rm-btn-primary rm-btn-sm flex items-center justify-center gap-1 hover:scale-[1.02] active:scale-[0.98] transition-all border-none">
                    <i class="ti ti-plus"></i> Create schedule
                </button>
            </div>
        </div>

        {{-- 2A. WEEKLY SCHEDULE GRID CARD --}}
        <div class="rm-card rm-grid-card">
            <div class="rm-grid-scroll-wrapper">
                <div class="rm-schedule-grid" id="rm-schedule-grid-container">
                    {{-- Header Row and Route rows will be dynamically drawn by JS --}}
                </div>
            </div>
        </div>

        {{-- 4A. CONFLICT DETECTION PANEL (INLINE VERSION REMOVED) --}}

        {{-- 2B. UPCOMING TRIPS TODAY LIST --}}
        <div class="rm-section-label">Upcoming trips today</div>
        <div class="rm-upcoming-list" id="rm-upcoming-trips-list">
            {{-- Loaded dynamically by JS --}}
        </div>
    </div>


    {{-- ==================== SCREEN CONTAINER 2: ROUTES & STOPS VIEW ==================== --}}
    <div id="rm-panel-stops" class="rm-panel-content hidden">
        <div class="rm-two-column-split">

            {{-- 3A. LEFT PANEL: ROUTE LIST --}}
            <div class="rm-card rm-left-panel">
                <div class="rm-panel-inner-header">
                    <span class="rm-inner-title" id="rm-active-routes-count">0 active routes</span>
                    <button class="rm-btn-outline rm-btn-xs" onclick="addNewRouteStub()">
                        <i class="ti ti-plus"></i> Add route
                    </button>
                </div>

                <div class="rm-route-cards-list" id="rm-route-cards-container">
                    {{-- Loaded dynamically by JS --}}
                </div>

                <div class="rm-panel-inner-footer">
                    <button class="rm-link-toggle" onclick="toggleSuspendedRoutes()">
                        <span id="suspended-toggle-label">Show suspended routes</span>
                    </button>
                </div>
            </div>

            {{-- 3B. RIGHT PANEL: ROUTE DETAIL + STOP EDITOR --}}
            <div class="rm-card rm-right-panel">
                <div class="rm-panel-inner-header justify-between flex-row w-full">
                    <div class="flex items-center gap-2">
                        <span class="rm-inner-title" id="rm-detail-route-title">Loading Route Details...</span>
                        <span class="rm-badge-green" id="rm-detail-route-status">Active</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button class="rm-btn-outline rm-btn-xs" onclick="editRouteDetails()">
                            <i class="ti ti-edit"></i> Edit route
                        </button>
                        <button class="rm-btn-outline rm-btn-xs rm-btn-danger-text"
                            onclick="toggleSuspendRouteDetail()">
                            <i class="ti ti-alert-triangle"></i> Suspend route
                        </button>
                    </div>
                </div>

                <div class="rm-right-panel-body">
                    {{-- STOP TIMELINE (LEFT SIDE: 55%) --}}
                    <div class="rm-stop-timeline-column">
                        <div class="rm-inner-label" id="rm-timeline-stops-count">Stop sequence — 0 stops</div>
                        <div class="rm-vertical-timeline" id="rm-stop-timeline-container">
                            {{-- Timelines nodes populated by JS --}}
                        </div>
                        <button class="rm-btn-dashed-add" onclick="openAddStopToRouteModal()">
                            <i class="ti ti-plus"></i> Add a stop to this route
                        </button>
                    </div>

                    {{-- MAP PREVIEW + STATS (RIGHT SIDE: 45%) --}}
                    <div class="rm-map-stats-column">
                        {{-- MAP PREVIEW --}}
                        <div class="rm-map-preview-card">
                            <div class="rm-map-canvas" id="rm-simulated-map-container">
                                {{-- SVG path drawn by JS --}}
                            </div>
                            <div class="rm-map-footer">
                                <a href="#" class="rm-map-link" onclick="viewLiveMapScreen(event)">
                                    <i class="ti ti-external-link"></i> View on live map
                                </a>
                            </div>
                        </div>

                        {{-- ROUTE STATS SUMMARY --}}
                        <div class="rm-stats-summary-wrapper" id="rm-route-stats-summary-container">
                            {{-- Loaded by JS --}}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>


    {{-- ==================== SECTION 4: CONFLICT DETECTION PANEL (SLIDE-IN SIDE VERSION REMOVED) ==================== --}}





    {{-- ==================== MODAL 4B: RESOLVE CONFLICT MODAL ==================== --}}
    <div id="rm-resolve-modal" class="rm-modal-overlay hidden">
        <div class="rm-modal-card rm-modal-card-resolve">
            {{-- Header --}}
            <div class="rm-modal-header">
                <span class="rm-modal-title-text" id="resolve-modal-title">Resolve conflict</span>
                <button class="rm-modal-close-btn" onclick="closeResolveModal()"><i class="ti ti-x"></i></button>
            </div>

            {{-- Body --}}
            <div class="rm-modal-body">
                {{-- Conflict Summary Card --}}
                <div class="rm-resolve-warning-card">
                    <div class="flex gap-2">
                        <i class="ti ti-alert-triangle icon-amber" style="font-size:16px;margin-top:2px;"></i>
                        <span id="resolve-modal-desc">
                            Loading conflict details...
                        </span>
                    </div>
                </div>

                <div class="rm-inner-label mt-4">Choose a resolution</div>

                <div class="rm-resolution-options-list">
                    {{-- Option 1 --}}
                    <label class="rm-resolution-option-card" id="res-option-1-card">
                        <input type="radio" name="res-choice" id="res-choice-reassign" value="reassign" checked
                            onchange="toggleResolutionChoiceFields()">
                        <div class="rm-res-card-content">
                            <div class="rm-res-title" id="resolve-reassign-title">Reassign driver</div>
                            <div class="rm-res-sub">Select a different driver from available pool</div>
                            <div class="rm-res-dropdown-wrapper mt-2 hidden" id="resolve-reassign-dropdown-wrap">
                                <select class="rm-form-select" id="resolve-select-driver">
                                    {{-- Loaded by JS --}}
                                </select>
                            </div>
                        </div>
                    </label>

                    {{-- Option 2 --}}
                    <label class="rm-resolution-option-card" id="res-option-2-card">
                        <input type="radio" name="res-choice" id="res-choice-adjust" value="adjust"
                            onchange="toggleResolutionChoiceFields()">
                        <div class="rm-res-card-content">
                            <div class="rm-res-title" id="resolve-adjust-title">Adjust departure time</div>
                            <div class="rm-res-sub" id="resolve-adjust-sub">Move to another available slot</div>
                            <div class="rm-res-time-wrapper mt-2 hidden" id="resolve-time-wrap">
                                <input type="time" class="rm-form-input" id="resolve-input-time" value="{{ $defaultDepartureTime }}">
                            </div>
                        </div>
                    </label>

                    {{-- Option 3 --}}
                    <label class="rm-resolution-option-card rm-res-option-danger" id="res-option-3-card">
                        <input type="radio" name="res-choice" id="res-choice-remove" value="remove"
                            onchange="toggleResolutionChoiceFields()">
                        <div class="rm-res-card-content">
                            <div class="rm-res-title text-red">Remove one of the conflicting schedules</div>
                            <div class="rm-res-sub" id="resolve-remove-sub">Delete the selected conflicting entry entirely</div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Footer --}}
            <div class="rm-modal-footer">
                <button class="rm-btn-outline rm-btn-sm" onclick="closeResolveModal()">Cancel</button>
                <button class="rm-btn-primary rm-btn-sm" onclick="applyConflictResolution()">
                    <i class="ti ti-check"></i> Apply resolution
                </button>
            </div>
        </div>
    </div>


    {{-- ==================== MODAL: ADD STOP TO ROUTE ==================== --}}
    <div id="rm-add-stop-modal" class="rm-modal-overlay hidden">
        <div class="rm-modal-card" style="max-width:440px;">
            <div class="rm-modal-header">
                <span class="rm-modal-title-text">Add stop to Route</span>
                <button class="rm-modal-close-btn" onclick="closeAddStopModal()"><i class="ti ti-x"></i></button>
            </div>
            <div class="rm-modal-body">
                <form id="rm-add-stop-form" onsubmit="handleAddStopSubmit(event)" class="rm-form-layout">
                    <div class="rm-form-field">
                        <label class="rm-form-label" for="as-name">Stop name</label>
                        <input class="rm-form-input" id="as-name" type="text" placeholder="e.g. San Joaquin Market"
                            required>
                    </div>
                    <div class="rm-form-field">
                        <label class="rm-form-label" for="as-landmark">Landmark note</label>
                        <input class="rm-form-input" id="as-landmark" type="text" placeholder="e.g. near Wet Market">
                    </div>
                    <div class="rm-form-field">
                        <label class="rm-form-label" for="as-boarding">Avg Boarding</label>
                        <input class="rm-form-input" id="as-boarding" type="number" value="{{ $defaultStopBoarding }}" min="0">
                    </div>
                    <div class="rm-form-field">
                        <label class="rm-form-label" for="as-alighting">Avg Alighting</label>
                        <input class="rm-form-input" id="as-alighting" type="number" value="{{ $defaultStopAlighting }}" min="0">
                    </div>
                    <div class="rm-form-field">
                        <label class="rm-form-label" for="as-dwell">Dwell time (sec)</label>
                        <input class="rm-form-input" id="as-dwell" type="number" value="{{ $defaultStopDwellSeconds }}" min="0">
                    </div>
                </form>
            </div>
            <div class="rm-modal-footer">
                <button class="rm-btn-outline rm-btn-sm" onclick="closeAddStopModal()">Cancel</button>
                <button class="rm-btn-primary rm-btn-sm" onclick="handleAddStopSubmit(event)">Save stop</button>
            </div>
        </div>
    </div>

</section>


{{-- ==================== SCOPED CSS STYLING ==================== --}}
<style>
    #screen-routes,
    #rm-schedule-modal,
    #rm-resolve-modal,
    #rm-add-stop-modal,
    #conflict-sliding-drawer {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        box-sizing: border-box;
    }

    #screen-routes *,
    #rm-schedule-modal *,
    #rm-resolve-modal *,
    #rm-add-stop-modal *,
    #conflict-sliding-drawer * {
        box-sizing: border-box;
    }

    /* ── GENERAL UTILITIES & LAYOUT ── */
    .rm-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 12px;
        overflow: hidden;
    }

    .rm-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
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

    .rm-btn-primary:hover {
        background: #002d62;
    }

    .rm-btn-outline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
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

    .rm-btn-outline:hover {
        background: var(--color-background-secondary);
    }

    .rm-btn-sm {
        height: 30px;
        padding: 0 10px;
        font-size: 12px;
    }

    .rm-btn-xs {
        height: 26px;
        padding: 0 8px;
        font-size: 11px;
        border-radius: 6px;
    }

    .rm-btn-outline-amber {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 26px;
        padding: 0 8px;
        background: transparent;
        color: #854F0B;
        border: 0.5px solid #F5C4B3;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
        white-space: nowrap;
    }

    .rm-btn-outline-amber:hover {
        background: #FAEEDA;
    }

    .rm-btn-danger-text {
        color: #A32D2D;
        border-color: #FCEBEB;
    }

    .rm-btn-danger-text:hover {
        background: #FCEBEB;
    }

    .rm-icon-btn-square {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border: 0.5px solid var(--color-border-secondary);
        background: var(--color-background-primary);
        color: var(--color-text-primary);
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.12s;
    }

    .rm-icon-btn-square:hover {
        background: var(--color-background-secondary);
    }

    /* ── PAGE HEADER ── */
    .rm-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .rm-h1 {
        font-size: 20px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
        line-height: 1.3;
    }

    .rm-subtitle {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin: 2px 0 0;
    }

    .rm-page-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .rm-btn-conflict {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 36px;
        padding: 0 14px;
        background: transparent;
        color: #854F0B;
        border: 1px solid #F5C4B3;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
    }

    .rm-btn-conflict:hover {
        background: #FAEEDA;
    }

    /* ── MAIN TAB ROW ── */
    .rm-tab-row {
        margin-bottom: 20px;
    }

    .rm-pill-group {
        display: inline-flex;
        background: var(--color-background-secondary);
        border: 0.5px solid var(--color-border-secondary);
        padding: 3px;
        border-radius: 99px;
        gap: 4px;
    }

    .rm-tab-btn {
        height: 28px;
        padding: 0 16px;
        border-radius: 99px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }

    .rm-tab-btn.active {
        background: #003F87;
        color: #ffffff;
    }

    /* ── SCHEDULE SUB-HEADER ── */
    .rm-sub-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .rm-sub-header-left,
    .rm-sub-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rm-date-selector {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--color-background-primary);
    }

    .rm-date-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text-primary);
        min-width: 170px;
        text-align: center;
    }

    /* ── WEEKLY SCHEDULE GRID ── */
    .rm-grid-scroll-wrapper {
        overflow-x: auto;
        width: 100%;
        scrollbar-width: thin;
    }

    .rm-schedule-grid {
        display: grid;
        grid-template-columns: 100px repeat(18, minmax(80px, 1fr));
        background: var(--color-background-primary);
        min-width: 1100px;
    }

    /* Header Cells */
    .rm-grid-hdr {
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-align: center;
        padding: 8px 4px;
        background: var(--color-background-secondary);
        border-bottom: 0.5px solid var(--color-border-tertiary);
        border-right: 0.5px solid var(--color-border-tertiary);
    }

    .rm-grid-hdr-empty {
        background: var(--color-background-secondary);
        border-bottom: 0.5px solid var(--color-border-tertiary);
        border-right: 0.5px solid var(--color-border-tertiary);
        width: 100px;
    }

    .rm-grid-hdr.peak {
        color: #003F87;
        font-weight: 700;
    }

    .rm-grid-hdr.current-col {
        background: #E6F1FB;
        position: relative;
    }

    /* Label Cells (leftmost) */
    .rm-grid-label-cell {
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 12px;
        border-right: 0.5px solid var(--color-border-tertiary);
        border-bottom: 0.5px solid var(--color-border-tertiary);
        width: 100px;
        background: var(--color-background-secondary);
    }

    .rm-grid-label-1 {
        color: #003F87;
    }

    .rm-grid-label-2 {
        color: #3B6D11;
    }

    .rm-grid-label-3 {
        color: #854F0B;
    }

    .rm-grid-label-4 {
        color: #A32D2D;
    }

    /* Empty Grid Intersection Cells */
    .rm-grid-cell {
        background: var(--color-background-primary);
        border-right: 0.5px solid var(--color-border-tertiary);
        border-bottom: 0.5px solid var(--color-border-tertiary);
        min-height: 68px;
        padding: 4px;
        cursor: pointer;
        position: relative;
        transition: background 0.12s;
    }

    .rm-grid-cell:hover {
        background: #F8F7F4;
    }

    .rm-grid-cell.current-col::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 50%;
        width: 1px;
        border-left: 1px dashed #003F87;
        opacity: 0.4;
        pointer-events: none;
        z-index: 1;
    }

    /* Trip Blocks */
    .rm-trip-block {
        width: 92%;
        margin: 2px auto;
        border-radius: 0 6px 6px 0;
        padding: 5px 7px;
        font-size: 11px;
        position: relative;
        z-index: 2;
        cursor: pointer;
        transition: transform 0.1s, box-shadow 0.1s;
    }

    .rm-trip-block:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    }

    .rm-trip-1 {
        background: #E6F1FB;
        border-left: 3px solid #003F87;
    }

    .rm-trip-1 .rm-trip-line1 {
        color: #0C447C;
    }

    .rm-trip-2 {
        background: #EAF3DE;
        border-left: 3px solid #3B6D11;
    }

    .rm-trip-2 .rm-trip-line1 {
        color: #3B6D11;
    }

    .rm-trip-3 {
        background: #FAEEDA;
        border-left: 3px solid #854F0B;
    }

    .rm-trip-3 .rm-trip-line1 {
        color: #854F0B;
    }

    .rm-trip-4 {
        background: #FCEBEB;
        border-left: 3px solid #E24B4A;
    }

    .rm-trip-4 .rm-trip-line1 {
        color: #A32D2D;
    }

    /* Conflict Block overrides */
    .rm-trip-conflict {
        background: #FCEBEB !important;
        border-left: 3px solid #E24B4A !important;
    }

    .rm-trip-conflict .rm-trip-line1 {
        color: #A32D2D !important;
    }

    .rm-trip-line1 {
        font-family: 'Courier New', Courier, monospace;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rm-trip-line2 {
        font-size: 10px;
        color: var(--color-text-secondary);
        margin-top: 2px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Capacity Tiny Pill */
    .rm-cap-tiny {
        padding: 0 4px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        line-height: 1.3;
    }

    .rm-cap-tiny.full {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .rm-cap-tiny.near-full {
        background: #FAEEDA;
        color: #854F0B;
    }

    /* Tooltips */
    .rm-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%) translateY(-6px);
        background: #ffffff;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 10px;
        padding: 10px 12px;
        width: 220px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        z-index: 999;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.15s, visibility 0.15s;
    }

    .rm-trip-block:hover .rm-tooltip {
        opacity: 1;
        visibility: visible;
    }

    .rm-tt-row {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        margin-bottom: 4px;
        color: var(--color-text-primary);
    }

    .rm-tt-row:last-child {
        margin-bottom: 0;
    }

    .rm-tt-lbl {
        color: var(--color-text-secondary);
    }

    .rm-tt-val {
        font-weight: 500;
    }

    .rm-tt-val.mono {
        font-family: 'Courier New', Courier, monospace;
    }

    .rm-tt-conflict-msg {
        background: #FCEBEB;
        color: #A32D2D;
        font-size: 10px;
        padding: 4px 6px;
        border-radius: 4px;
        margin-top: 5px;
        border-left: 2px solid #E24B4A;
        white-space: normal;
        line-height: 1.3;
    }

    /* ── UPCOMING TRIPS TODAY LIST ── */
    .rm-section-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 24px;
        margin-bottom: 12px;
    }

    .rm-upcoming-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .rm-upcoming-row {
        display: flex;
        align-items: center;
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 10px;
        padding: 10px 14px;
        gap: 12px;
        transition: background 0.12s;
    }

    .rm-upcoming-row:hover {
        background: #EEF3FF;
    }

    /* Route badges */
    .rm-badge-pill {
        padding: 2px 7px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 700;
        text-align: center;
        min-width: 54px;
    }

    .rm-badge-pill.route-1 {
        background: #E6F1FB;
        color: #0C447C;
    }

    .rm-badge-pill.route-2 {
        background: #EAF3DE;
        color: #3B6D11;
    }

    .rm-badge-pill.route-3 {
        background: #FAEEDA;
        color: #854F0B;
    }

    .rm-badge-pill.route-4 {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .rm-time-txt {
        font-size: 13px;
        font-weight: 600;
        color: #003F87;
        min-width: 65px;
    }

    .rm-driver-txt {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text-primary);
        min-width: 120px;
    }

    .rm-bus-txt {
        font-size: 12px;
        font-family: 'Courier New', Courier, monospace;
        color: var(--color-text-secondary);
        min-width: 80px;
    }

    /* Dynamic chip classes */
    .rm-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
    }

    .rm-chip-green {
        background: #EAF3DE;
        color: #3B6D11;
    }

    .rm-chip-amber {
        background: #FAEEDA;
        color: #854F0B;
    }

    .rm-chip-red {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .rm-chip-gray {
        background: #F1EFE8;
        color: #5F5E5A;
    }

    .rm-btn-link-view {
        background: transparent;
        border: none;
        color: #003F87;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: auto;
    }

    .rm-btn-link-view:hover {
        text-decoration: underline;
    }

    /* ── TWO-COLUMN SPLIT (ROUTES & STOPS) ── */
    .rm-two-column-split {
        display: flex;
        gap: 16px;
        width: 100%;
        align-items: flex-start;
    }

    .rm-left-panel {
        width: 38%;
        display: flex;
        flex-direction: column;
        background: var(--color-background-primary);
    }

    .rm-right-panel {
        width: 62%;
        background: var(--color-background-primary);
    }

    .rm-panel-inner-header {
        padding: 14px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--color-background-secondary);
        shrink-0: true;
    }

    .rm-inner-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-panel-inner-footer {
        padding: 12px 16px;
        border-top: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-secondary);
    }

    .rm-link-toggle {
        background: transparent;
        border: none;
        color: var(--color-text-secondary);
        font-size: 12px;
        cursor: pointer;
        font-weight: 500;
    }

    .rm-link-toggle:hover {
        color: #003F87;
    }

    /* Route cards list */
    .rm-route-cards-list {
        display: flex;
        flex-direction: column;
    }

    .rm-route-card {
        padding: 14px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        cursor: pointer;
        transition: background 0.1s, border-left 0.1s;
        background: var(--color-background-primary);
        border-left: 3px solid transparent;
    }

    .rm-route-card:last-child {
        border-bottom: none;
    }

    .rm-route-card:hover {
        background: #EEF3FF;
    }

    .rm-route-card.selected {
        background: #F0F5FF;
        border-left: 3px solid #003F87;
        padding-left: 13px;
        /* compensate for left border */
    }

    .rm-rc-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }

    .rm-rc-title {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        font-weight: 600;
    }

    .rm-rc-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .rm-rc-mid {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-bottom: 6px;
        font-weight: 500;
    }

    .rm-rc-stats {
        display: flex;
        gap: 12px;
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-bottom: 6px;
    }

    .rm-rc-stat-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .rm-rc-btm {
        font-size: 11px;
        font-weight: 500;
    }

    /* Right Panel Layout */
    .rm-right-panel-body {
        display: flex;
        padding: 16px;
        gap: 16px;
    }

    .rm-stop-timeline-column {
        width: 55%;
        display: flex;
        flex-direction: column;
    }

    .rm-map-stats-column {
        width: 45%;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .rm-inner-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 12px;
    }

    /* ── VERTICAL TIMELINE ── */
    .rm-vertical-timeline {
        display: flex;
        flex-direction: column;
    }

    .rm-timeline-node {
        display: flex;
        gap: 12px;
    }

    .rm-node-left {
        width: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
    }

    .rm-node-circle {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ffffff;
        border: 1.5px solid #003F87;
        margin-top: 4px;
        z-index: 2;
        position: relative;
    }

    .rm-node-circle.served {
        background: #003F87;
        border: none;
    }

    .rm-node-circle.current-next {
        background: #ffffff;
        border: 2px solid #003F87;
    }

    .rm-node-circle.future {
        background: #E6F1FB;
        border: 1.5px solid #003F87;
    }

    .rm-node-circle.terminus {
        width: 14px;
        height: 14px;
        background: #003F87;
        border: none;
        margin-top: 3px;
    }

    .rm-node-circle-label {
        font-family: 'Courier New', Courier, monospace;
        font-size: 9px;
        font-weight: 700;
        color: #003F87;
        position: absolute;
        top: 14px;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
    }

    .rm-node-line {
        width: 2px;
        flex-grow: 1;
        background: #E6F1FB;
        z-index: 1;
        margin-top: 2px;
        margin-bottom: -2px;
        min-height: 50px;
    }

    .rm-node-line.served-conn {
        background: #003F87;
    }

    .rm-node-right {
        flex-grow: 1;
        padding-bottom: 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .rm-timeline-node:last-child .rm-node-right {
        border-bottom: none;
    }

    .rm-stop-name-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rm-stop-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-stop-landmark {
        font-size: 11px;
        color: var(--color-text-secondary);
        font-style: italic;
    }

    .rm-stop-drag {
        color: var(--color-text-secondary);
        cursor: grab;
        margin-left: auto;
        font-size: 16px;
    }

    .rm-stop-drag:active {
        cursor: grabbing;
    }

    .rm-stop-delete {
        color: var(--color-text-secondary);
        cursor: pointer;
        font-size: 16px;
        transition: color 0.12s;
    }

    .rm-stop-delete:hover {
        color: #A32D2D;
    }

    .rm-stop-stats-row {
        display: flex;
        gap: 16px;
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-top: 4px;
    }

    .rm-stop-stat-val {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .rm-btn-dashed-add {
        width: 100%;
        height: 36px;
        background: transparent;
        color: #003F87;
        border: 1px dashed #003F87;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        cursor: flex;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-top: 12px;
        transition: background 0.15s;
    }

    .rm-btn-dashed-add:hover {
        background: #E6F1FB;
    }

    /* ── MAP PREVIEW ── */
    .rm-map-preview-card {
        border-radius: 10px;
        overflow: hidden;
        border: 0.5px solid var(--color-border-tertiary);
        background: #ffffff;
    }

    .rm-map-canvas {
        height: 200px;
        background: #E8EEF4;
        /* Map-like light background */
        position: relative;
        overflow: hidden;
    }

    .rm-map-canvas svg {
        width: 100%;
        height: 100%;
    }

    .rm-map-footer {
        padding: 8px 12px;
        background: var(--color-background-secondary);
        border-top: 0.5px solid var(--color-border-tertiary);
        display: flex;
        justify-content: flex-end;
    }

    .rm-map-link {
        font-size: 12px;
        color: #003F87;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .rm-map-link:hover {
        text-decoration: underline;
    }

    .rm-map-label-box {
        background: #ffffff;
        border: 0.5px solid var(--color-border-secondary);
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    /* ── ROUTE STATS SUMMARY ── */
    .rm-stats-summary-wrapper {
        display: flex;
        flex-direction: column;
    }

    .rm-stat-summary-row {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        padding: 8px 0;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        color: var(--color-text-primary);
    }

    .rm-stat-summary-row:last-of-type {
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .rm-stat-sum-lbl {
        color: var(--color-text-secondary);
    }

    .rm-stat-sum-val {
        font-weight: 600;
    }

    .rm-assigned-buses-title {
        font-size: 11px;
        font-weight: 600;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 14px;
        margin-bottom: 8px;
    }

    .rm-bus-pills-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }

    .rm-bus-pill {
        background: #E6F1FB;
        color: #0C447C;
        border-radius: 99px;
        font-size: 11px;
        font-family: 'Courier New', Courier, monospace;
        font-weight: 700;
        padding: 3px 8px;
    }

    .rm-link-manage-assign {
        font-size: 12px;
        color: #003F87;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .rm-link-manage-assign:hover {
        text-decoration: underline;
    }

    /* ── CONFLICT DETECTION INLINE/SLIDING PANELS ── */
    .rm-conflict-panel-card {
        border: 0.5px solid #F5C4B3;
        border-radius: 12px;
        background: var(--color-background-primary);
        overflow: hidden;
        margin-top: 16px;
        display: none;
        /* Controlled by JS show/hide */
    }

    .rm-conflict-panel-header {
        padding: 12px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #FFFBF0;
    }

    .rm-conflict-header-left {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rm-conflict-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rm-panel-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-badge-amber {
        background: #FAEEDA;
        color: #854F0B;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 700;
    }

    .rm-badge-red {
        background: #FCEBEB;
        color: #A32D2D;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 700;
    }

    .rm-badge-green {
        background: #EAF3DE;
        color: #3B6D11;
        padding: 2px 8px;
        border-radius: 99px;
        font-size: 10px;
        font-weight: 700;
    }

    .rm-icon-btn-close {
        width: 22px;
        height: 22px;
        border-radius: 4px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
    }

    .rm-icon-btn-close:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .rm-conflict-summary-row {
        display: flex;
        gap: 8px;
        padding: 10px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
    }

    .rm-conflict-list {
        display: flex;
        flex-direction: column;
    }

    .rm-conflict-row {
        padding: 14px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
        transition: background 0.1s;
    }

    .rm-conflict-row:last-child {
        border-bottom: none;
    }

    .rm-conflict-row:hover {
        background: #FFF9F9;
    }

    .rm-conflict-row.severity-medium:hover {
        background: #FFFDF5;
    }

    .rm-conflict-row-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }

    .rm-severity-chip {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .rm-severity-chip.high {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .rm-severity-chip.medium {
        background: #FAEEDA;
        color: #854F0B;
    }

    .rm-conflict-entity {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text-primary);
        margin-bottom: 3px;
    }

    .rm-conflict-desc {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-bottom: 8px;
        line-height: 1.4;
    }

    .rm-conflict-affected-row {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .rm-affected-chip {
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 600;
        background: var(--color-background-secondary);
        color: var(--color-text-primary);
        border: 0.5px solid var(--color-border-tertiary);
        cursor: pointer;
    }

    .rm-affected-chip:hover {
        background: #EEF3FF;
        color: #003F87;
    }

    /* ── DRAWERS (SLIDING PANEL) ── */
    .rm-drawer-overlay {
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(2px);
    }

    .rm-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 360px;
        background: var(--color-background-primary);
        box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
        z-index: 1001;
        display: flex;
        flex-direction: column;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .rm-drawer.rm-drawer--open {
        transform: translateX(0);
    }

    .rm-drawer-inner {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .rm-drawer-top-bar {
        padding: 14px 16px;
        border-b: 0.5px solid var(--color-border-tertiary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--color-background-secondary);
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .rm-drawer-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-drawer-content {
        flex-grow: 1;
        overflow-y: auto;
    }

    .rm-icon-btn-close-large {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
    }

    .rm-icon-btn-close-large:hover {
        background: rgba(0, 0, 0, 0.05);
        color: var(--color-text-primary);
    }


    /* ── MODALS (CREATE/EDIT & RESOLVE) ── */
    .rm-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
        overflow-y: auto;
    }

    .rm-modal-card {
        background: #ffffff;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 16px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        animation: rm-slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .rm-modal-card-resolve {
        max-width: 500px;
    }

    @keyframes rm-slide-up {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .rm-modal-header {
        padding: 16px 20px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .rm-modal-title-text {
        font-size: 15px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-modal-close-btn {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .rm-modal-close-btn:hover {
        background: var(--color-background-secondary);
        color: var(--color-text-primary);
    }

    .rm-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex-grow: 1;
    }

    .rm-form-layout {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .rm-form-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 100%;
    }

    .rm-form-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--color-text-secondary);
    }

    .rm-form-select,
    .rm-form-input {
        width: 100%;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-secondary);
        font-size: 13px;
        color: var(--color-text-primary);
        background: #ffffff;
        outline: none;
        transition: border-color 0.15s;
    }

    .rm-form-select:focus,
    .rm-form-input:focus {
        border-color: #003F87;
    }

    .rm-select-wrapper {
        position: relative;
        width: 100%;
    }

    .rm-helper-text {
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-top: 2px;
    }

    .rm-license-expiry-warning-text {
        font-size: 11px;
        color: #BA7517;
        font-weight: 500;
        margin-top: 2px;
    }

    /* Conflict warning card inside forms */
    .rm-conflict-warning-card {
        background: #FAEEDA;
        border-left: 3px solid #BA7517;
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 12px;
        color: #854F0B;
        line-height: 1.4;
    }

    /* Day Pill Toggles */
    .rm-day-pill-row {
        display: flex;
        gap: 4px;
    }

    .rm-day-pill-toggle {
        flex-grow: 1;
        cursor: pointer;
    }

    .rm-day-pill-toggle input {
        display: none;
    }

    .rm-day-pill-toggle span {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        border-radius: 6px;
        border: 0.5px solid var(--color-border-secondary);
        font-size: 12px;
        font-weight: 600;
        background: #ffffff;
        color: var(--color-text-secondary);
        transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .rm-day-pill-toggle input:checked+span {
        background: #003F87;
        border-color: #003F87;
        color: #ffffff;
    }

    /* Modal Footer */
    .rm-modal-footer {
        padding: 14px 20px;
        border-top: 0.5px solid var(--color-border-tertiary);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    .rm-btn-link-danger {
        background: transparent;
        border: none;
        color: #A32D2D;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .rm-btn-link-danger:hover {
        text-decoration: underline;
    }

    /* ── RESOLUTION MODAL STYLES ── */
    .rm-resolve-warning-card {
        background: #FEF3E0;
        border-left: 3px solid #BA7517;
        border-radius: 8px;
        padding: 12px;
        font-size: 12px;
        color: #854F0B;
        line-height: 1.4;
    }

    .rm-resolution-options-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 8px;
    }

    .rm-resolution-option-card {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 10px;
        padding: 12px 14px;
        cursor: pointer;
        transition: border-color 0.15s, background-color 0.15s;
        background: #ffffff;
    }

    .rm-resolution-option-card:hover {
        background: #F8F7F4;
    }

    .rm-resolution-option-card input[type="radio"] {
        margin-top: 3px;
        accent-color: #003F87;
    }

    .rm-resolution-option-card.rm-res-option-danger {
        border-color: #F09595;
    }

    .rm-resolution-option-card.rm-res-option-danger:hover {
        background: #FFF5F5;
    }

    .rm-res-card-content {
        flex-grow: 1;
    }

    .rm-res-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--color-text-primary);
    }

    .rm-res-sub {
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-top: 2px;
    }

    .rm-res-dropdown-wrapper,
    .rm-res-time-wrapper {
        width: 100%;
        max-width: 250px;
    }

    /* Colors and helpers */
    .icon-amber {
        color: #854F0B;
    }

    .text-red {
        color: #A32D2D !important;
    }

    .hidden {
        display: none !important;
    }
</style>
