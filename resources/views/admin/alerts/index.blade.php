{{-- ==================== SERVICE ALERT MANAGEMENT SCREEN ==================== --}}
<section id="screen-alerts" class="hidden"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-background-tertiary:#F4F3EF;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-secondary:#D6D3C9;--color-border-tertiary:#E8E6DF;">

    {{-- PAGE HEADER ROW --}}
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Service Alerts</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-slate-600 font-bold">Service Alerts</span>
                </div>
                <div class="flex items-center gap-2 mt-1 select-none">
                    <span id="active-alerts-count" class="inline-flex rounded-full bg-[#FCEBEB] px-2.5 py-0.5 text-[9px] font-bold text-[#A32D2D] uppercase tracking-wider">0 active alerts</span>
                    <span class="am-last-broadcast text-[10px] text-slate-400 font-semibold">Last broadcast: None today</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="#alerts-history" onclick="switchScreen('alerts-history'); return false;" class="am-btn-outline inline-flex items-center gap-1.5 select-none no-underline">
                    <i class="ti ti-history"></i> Alert history
                </a>
                <button class="am-btn-primary" onclick="clearComposerForm()">
                    <i class="ti ti-bell-plus"></i> New alert
                </button>
            </div>
        </div>
    </div>

    {{-- MAIN LAYOUT --}}
    <div class="am-main-grid">
        
        {{-- LEFT COLUMN: ALERTS FEED (60%) --}}
        <div class="am-feed-column">
            
            {{-- 2A. FILTER BAR --}}
            <div class="am-filter-bar">
                <div class="am-status-tab-group">
                    <button class="am-status-tab-btn active" data-tab="All" onclick="setFeedStatusTab('All')">
                        <span id="filter-all-dot" class="am-red-dot" style="display: none;"></span>All
                    </button>
                    <button class="am-status-tab-btn" data-tab="Active" onclick="setFeedStatusTab('Active')">
                        Active · <span id="tab-active-count">0</span>
                    </button>
                    <button class="am-status-tab-btn" data-tab="Resolved" onclick="setFeedStatusTab('Resolved')">
                        Resolved · <span id="tab-resolved-count">0</span>
                    </button>
                    <button class="am-status-tab-btn" data-tab="Scheduled" onclick="setFeedStatusTab('Scheduled')">
                        Scheduled · <span id="tab-scheduled-count">0</span>
                    </button>
                </div>

                <div class="am-filter-right">
                    <select class="am-select" id="feed-type-filter" onchange="onFeedTypeFilterChange(this.value)">
                        <option value="All">All types</option>
                        <option value="Delay">Delay</option>
                        <option value="Route change">Route change</option>
                        <option value="Suspension">Suspension</option>
                        <option value="Breakdown">Breakdown</option>
                        <option value="Weather">Weather</option>
                        <option value="Emergency">Emergency</option>
                    </select>

                    <div class="am-search-wrapper">
                        <i class="ti ti-search am-search-icon"></i>
                        <input id="feed-search-input" type="text" class="am-search-input" placeholder="Search alerts…" oninput="onFeedSearchInput(this.value)">
                    </div>
                </div>
            </div>

            {{-- 2B. ACTIVE ALERTS SECTION --}}
            <div id="active-section-container">
                <div class="am-section-label-row">
                    <span class="am-section-label">Active</span>
                    <a href="#" class="am-link-resolve-all" onclick="markAllAlertsResolved(event)">Mark all resolved</a>
                </div>
                
                {{-- Dynamic Feed cards --}}
                <div class="am-alerts-feed-list" id="active-alerts-feed">
                    {{-- Loaded dynamically by alerts.js --}}
                </div>
            </div>

            {{-- 2C. RESOLVED ALERTS SECTION --}}
            <div id="resolved-section-container" class="am-collapsible-section">
                <div class="am-collapsible-header" onclick="toggleResolvedSection()">
                    <div class="am-collapsible-header-left">
                        <i id="resolved-chevron" class="ti ti-chevron-right"></i>
                        <span id="resolved-count-label" class="am-collapsible-title">Resolved today (0)</span>
                    </div>
                    <a href="#alerts-history" onclick="switchScreen('alerts-history'); return false;" class="am-collapsible-link">View all</a>
                </div>
                
                <div id="resolved-rows-container" class="am-collapsible-content hidden">
                    <div class="am-resolved-list" id="resolved-rows-list">
                        {{-- Loaded by JS --}}
                    </div>
                </div>
            </div>

            {{-- 2D. SCHEDULED ALERTS SECTION --}}
            <div id="scheduled-section-container" class="am-collapsible-section">
                <div class="am-collapsible-header" onclick="toggleScheduledSection()">
                    <div class="am-collapsible-header-left">
                        <i id="scheduled-chevron" class="ti ti-chevron-right"></i>
                        <span id="scheduled-count-label" class="am-collapsible-title">Scheduled (0)</span>
                    </div>
                </div>
                
                <div id="scheduled-cards-container" class="am-collapsible-content hidden">
                    <div class="am-scheduled-list-wrapper" id="scheduled-cards-list">
                        {{-- Loaded by JS --}}
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT COLUMN: ALERT COMPOSER (40%) --}}
        <div class="am-composer-column">
            
            <div class="am-card" id="composer-card">
                
                {{-- COMPOSER HEADER --}}
                <div class="am-composer-hdr">
                    <div class="am-composer-hdr-left">
                        <i class="ti ti-bell-plus"></i>
                        <h2 class="am-composer-title" id="composer-header-title">New alert</h2>
                    </div>
                    <a href="#" class="am-link-clear" onclick="event.preventDefault(); clearComposerForm();">
                        <i class="ti ti-refresh"></i> Clear form
                    </a>
                </div>

                {{-- COMPOSER BODY --}}
                <div class="am-composer-body">
                    
                    {{-- FIELD 1: ALERT TYPE SELECTOR (ICON GRID) --}}
                    <div class="am-field">
                        <label class="am-label">Alert type</label>
                        <div class="am-type-grid">
                            <div class="am-type-card selected" data-type="Delay" onclick="selectComposerType('Delay')">
                                <i class="ti ti-clock-exclamation"></i>
                                <span>Delay</span>
                            </div>
                            <div class="am-type-card" data-type="Route change" onclick="selectComposerType('Route change')">
                                <i class="ti ti-route"></i>
                                <span>Route change</span>
                            </div>
                            <div class="am-type-card" data-type="Suspension" onclick="selectComposerType('Suspension')">
                                <i class="ti ti-ban"></i>
                                <span>Suspension</span>
                            </div>
                            <div class="am-type-card" data-type="Breakdown" onclick="selectComposerType('Breakdown')">
                                <i class="ti ti-tool"></i>
                                <span>Breakdown</span>
                            </div>
                            <div class="am-type-card" data-type="Weather" onclick="selectComposerType('Weather')">
                                <i class="ti ti-cloud-storm"></i>
                                <span>Weather</span>
                            </div>
                            <div class="am-type-card" data-type="Emergency" onclick="selectComposerType('Emergency')">
                                <i class="ti ti-alert-octagon"></i>
                                <span>Emergency</span>
                            </div>
                        </div>
                    </div>

                    {{-- FIELD 2: SEVERITY SEGMENTED CONTROL --}}
                    <div class="am-field">
                        <label class="am-label">Severity</label>
                        <div class="am-sev-pill-row">
                            <button type="button" class="am-sev-pill pill-low" data-severity="Low" onclick="selectComposerSeverity('Low')">Low</button>
                            <button type="button" class="am-sev-pill pill-medium active" data-severity="Medium" onclick="selectComposerSeverity('Medium')">Medium</button>
                            <button type="button" class="am-sev-pill pill-high" data-severity="High" onclick="selectComposerSeverity('High')">High</button>
                            <button type="button" class="am-sev-pill pill-emergency" data-severity="Emergency" onclick="selectComposerSeverity('Emergency')">Emergency</button>
                        </div>

                        {{-- Emergency active elements --}}
                        <div id="composer-emergency-banner" class="am-emergency-warning-banner hidden">
                            <i class="ti ti-alert-octagon"></i>
                            <span>Emergency broadcasts to all users immediately and cannot be undone.</span>
                        </div>
                    </div>

                    {{-- FIELD 3: ALERT TITLE --}}
                    <div class="am-field">
                        <div class="am-label-row">
                            <label class="am-label" for="composer-title">Alert title</label>
                        </div>
                        <input type="text" id="composer-title" class="am-input" placeholder="e.g. Route 2 service advisory" maxlength="80" oninput="handleComposerTitleInput(event)">
                        <div class="am-char-counter-row">
                            <span id="composer-title-counter" class="am-char-counter">0 / 80</span>
                        </div>
                    </div>

                    {{-- FIELD 4: MESSAGE BODY --}}
                    <div class="am-field">
                        <div class="am-label-row">
                            <label class="am-label" for="composer-message">Message</label>
                        </div>
                        <textarea id="composer-message" class="am-textarea" rows="4" placeholder="Describe the situation and what commuters should expect or do…" maxlength="280" oninput="handleComposerMessageInput(event)"></textarea>
                        <div class="am-char-counter-row">
                            <span id="composer-message-counter" class="am-char-counter">0 / 280</span>
                        </div>
                    </div>

                    {{-- FIELD 5: AFFECTED ROUTES --}}
                    <div class="am-field">
                        <label class="am-label">Affected routes</label>
                        {{-- ISSUE-045 FIX: Route pills are now rendered dynamically from the DB by alerts.js --}}
                        <div class="am-route-pill-row" id="composer-route-pills-row">
                            <button type="button" class="am-route-pill" data-route="All official routes" onclick="toggleComposerRoute('All official routes')">All official routes</button>
                            {{-- Dynamic per-route pills injected here by loadRoutesIntoComposer() --}}
                        </div>
                    </div>
                    {{-- FIELD 6: SEND TIMING --}}
                    <div class="am-field">
                        <label class="am-label">Send</label>
                        <div class="am-timing-radio-row">
                            <label class="am-timing-radio-card">
                                <input type="radio" name="timing" id="rad-send-now" checked onclick="setComposerTiming('now')">
                                <span>Send now</span>
                            </label>
                            <label class="am-timing-radio-card">
                                <input type="radio" name="timing" id="rad-send-later" onclick="setComposerTiming('later')">
                                <span>Schedule for later</span>
                            </label>
                        </div>

                        <div id="schedule-time-container" class="am-schedule-time-block mt-3 hidden">
                            <input type="datetime-local" id="schedule-time-input" class="am-input" onchange="onComposerScheduleTimeChange(this.value)">
                            <div class="am-helper-text mt-1.5">Alert will be queued and broadcast at the selected time</div>
                            <div id="schedule-preview-chip" class="am-route-pill-display selected-all mt-2 inline-block hidden">Scheduled Preview</div>
                        </div>
                    </div>

                    <hr class="am-divider">

                    {{-- FIELD 7: ROUTE SUSPENSION TOGGLE --}}
                    <div class="am-toggle-row" id="suspension-toggle-row">
                        <div class="am-toggle-label-block">
                            <span class="am-toggle-title" id="suspension-label">Activate Route Suspension</span>
                            <span class="am-toggle-subtitle">Operational action that blocks new dispatches for the selected route(s)</span>
                        </div>
                        <div class="am-toggle-switch" onclick="toggleComposerSuspension()">
                            <div id="toggle-suspension-track" class="am-toggle-track">
                                <div class="am-toggle-thumb"></div>
                            </div>
                        </div>
                    </div>

                    <div id="suspension-policy-helper" class="am-helper-text hidden">
                        This alert is informational only. No operational changes will be made.
                    </div>

                    {{-- Route suspension warning card --}}
                    <div id="suspension-warning-card" class="am-warning-card mt-3 hidden animate-fade-in-up">
                        <i class="ti ti-alert-triangle text-[#A32D2D] text-lg"></i>
                        <span class="text-xs text-[#A32D2D]" id="suspension-warning-text">New dispatches on the selected route(s) will be blocked immediately. Existing trips will continue until completed.</span>
                    </div>

                </div>

                {{-- COMPOSER FOOTER --}}
                <div class="am-composer-ftr">
                    <button class="am-btn-primary w-full text-center" id="btn-broadcast-alert" onclick="triggerComposerBroadcast()">
                        <i class="ti ti-send"></i> Broadcast alert
                    </button>
                    <a href="#" class="am-link-draft" onclick="event.preventDefault(); GoPasigUI.alert('Alert saved as Draft.');">Save as draft</a>
                </div>

            </div>

        </div>

    </div>

    {{-- ==================== SECTION 4A: BROADCAST CONFIRMATION OVERLAY ==================== --}}
    <div id="broadcast-overlay" class="am-overlay-wrapper hidden">
        
        {{-- CONFIRM OVERLAY CARD --}}
        <div id="broadcast-overlay-card" class="am-confirm-card animate-fade-in-up">
            <div class="am-confirm-hdr">
                <div id="confirm-icon-circle" class="am-confirm-icon-circle confirm-circle-info">
                    <i class="ti ti-bell"></i>
                </div>
                <h3 id="confirm-title" class="am-confirm-title">Broadcast this alert?</h3>
                <p id="confirm-sub" class="am-confirm-subtitle">This will notify commuters and drivers immediately.</p>
            </div>

            <div class="am-confirm-summary-block">
                <div class="am-confirm-row">
                    <span class="am-confirm-lbl">Type</span>
                    <span class="am-confirm-val" id="confirm-sum-type">Delay</span>
                </div>
                <div class="am-confirm-row">
                    <span class="am-confirm-lbl">Severity</span>
                    <span class="am-confirm-val" id="confirm-sum-severity">Medium</span>
                </div>
                <div class="am-confirm-row">
                    <span class="am-confirm-lbl">Affects</span>
                    <span class="am-confirm-val" id="confirm-sum-routes">Select official route</span>
                </div>
                <div class="am-confirm-row">
                    <span class="am-confirm-lbl">Notifying</span>
                    <span class="am-confirm-val" id="confirm-sum-notifying">0 commuters + 0 drivers</span>
                </div>
                <div class="am-confirm-row" style="border-bottom:none;">
                    <span class="am-confirm-lbl">Route suspension</span>
                    <span class="am-confirm-val" id="confirm-sum-suspension">No</span>
                </div>
            </div>

            {{-- Emergency warning banner inside overlay --}}
            <div id="confirm-emergency-warning-card" class="am-warning-card mb-4 hidden">
                <i class="ti ti-alert-octagon text-[#A32D2D] text-lg"></i>
                <span class="text-xs text-[#A32D2D]">Emergency alerts cannot be retracted after broadcast. Confirm only if the situation is verified.</span>
            </div>

            <div class="am-confirm-btns">
                <button class="am-btn-outline flex-1" onclick="hideBroadcastConfirmation()">Cancel</button>
                <button class="am-btn-primary flex-1 text-white" id="btn-confirm-broadcast" onclick="confirmBroadcast()">
                    <i class="ti ti-send"></i> Confirm broadcast
                </button>
            </div>
        </div>

        {{-- ==================== SECTION 4B: BROADCAST RECEIPT ==================== --}}
        <div id="receipt-confirmation-card" class="am-confirm-card hidden animate-fade-in-up">
            <div class="am-confirm-hdr">
                <div class="am-confirm-icon-circle confirm-circle-success">
                    <i class="ti ti-check"></i>
                </div>
                <h3 id="receipt-title" class="am-confirm-title text-[#3B6D11]">Alert broadcast successfully</h3>
                <p id="receipt-time-label" class="am-confirm-subtitle">Dec 10, 2025 · 9:14 AM</p>
            </div>

            <div class="am-receipt-stats-row mb-6 justify-center flex gap-3" id="receipt-stats-row">
                <span class="am-route-pill-display selected-all" id="receipt-stat-commuters">0 commuters notified</span>
                <span class="am-route-pill-display selected-b" id="receipt-stat-drivers">0 drivers notified</span>
                <span class="am-route-pill-display selected-a" id="receipt-stat-suspended" style="display:none;">Selected route suspended</span>
            </div>

            <div class="am-confirm-btns mt-6">
                <button id="receipt-primary-action" class="am-btn-outline flex-1" onclick="viewBroadcastAlertInFeed()">
                    <i class="ti ti-arrow-right"></i> View alert in feed
                </button>
                <button id="receipt-secondary-action" class="am-btn-primary flex-1 text-white" onclick="closeBroadcastReceipt()">
                    <i class="ti ti-bell-plus"></i> Create another alert
                </button>
            </div>
        </div>

    </div>

</section>

{{-- ==================== SCOPED CSS STYLING ==================== --}}
<style>
    /* Scoped tokens and namespace */
    #screen-alerts,
    #broadcast-overlay,
    #history-full-view {
        --color-background-primary: #ffffff;
        --color-background-secondary: #F8F7F4;
        --color-background-tertiary: #F4F3EF;
        --color-text-primary: #1A1917;
        --color-text-secondary: #5F5E5A;
        --color-border-secondary: #D6D3C9;
        --color-border-tertiary: #E8E6DF;
        
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        box-sizing: border-box;
    }

    #screen-alerts *,
    #broadcast-overlay *,
    #history-full-view * {
        box-sizing: border-box;
    }

    /* General Layout */
    .am-main-grid {
        display: flex;
        gap: 16px;
        align-items: flex-start;
    }

    .am-feed-column {
        width: 60%;
    }

    .am-composer-column {
        width: 40%;
        position: sticky;
        top: 20px;
    }

    .am-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Page Header */
    .am-page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .am-h1 {
        font-size: 20px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
    }

    .am-subtitle-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }

    .am-last-broadcast {
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    .am-page-header-right {
        display: flex;
        gap: 8px;
    }

    /* Buttons */
    .am-btn-primary {
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

    .am-btn-primary:hover {
        background: #002d62;
    }

    .am-btn-primary:disabled,
    .am-btn-primary.is-loading {
        cursor: wait;
        opacity: 0.78;
    }

    .am-spin {
        animation: am-spin 0.8s linear infinite;
    }

    .am-btn-outline {
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

    .am-btn-outline:hover {
        background: var(--color-background-secondary);
    }

    .am-btn-sm {
        height: 30px;
        padding: 0 10px;
        font-size: 12px;
    }

    /* Filter Bar */
    .am-filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .am-status-tab-group {
        display: inline-flex;
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 99px;
        padding: 3px;
        gap: 2px;
    }

    .am-status-tab-btn {
        height: 28px;
        padding: 0 12px;
        border-radius: 99px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
    }

    .am-status-tab-btn.active {
        background: #003F87;
        color: #ffffff;
    }

    .am-red-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #E24B4A;
        border-radius: 50%;
        margin-right: 5px;
    }

    .am-filter-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .am-select {
        height: 36px;
        padding: 0 10px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 13px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        cursor: pointer;
    }

    .am-select:focus {
        border-color: #003F87;
    }

    .am-search-wrapper {
        position: relative;
        width: 180px;
    }

    .am-search-icon {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 14px;
        pointer-events: none;
    }

    .am-search-input {
        width: 100%;
        height: 36px;
        padding: 0 10px 0 28px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 13px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        transition: border-color 0.15s;
    }

    .am-search-input:focus {
        border-color: #003F87;
    }

    /* Section Labels */
    .am-section-label-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        margin-top: 4px;
    }

    .am-section-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .am-link-resolve-all {
        font-size: 12px;
        font-weight: 500;
        color: #003F87;
        text-decoration: none;
    }

    .am-link-resolve-all:hover {
        text-decoration: underline;
    }

    /* Alert Cards Feed */
    .am-alerts-feed-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .am-alert-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 0 12px 12px 0;
        border-left-width: 3px;
        border-left-style: solid;
        overflow: hidden;
    }

    .am-card-inner {
        padding: 14px 16px;
    }

    /* Severity borders */
    .border-emergency { border-left-color: #E24B4A; }
    .border-high { border-left-color: #D85A30; }
    .border-medium { border-left-color: #BA7517; }
    .border-low { border-left-color: #185FA5; }

    /* Bg Tints */
    .bg-emergency-tint {
        background-color: #FFFBFB;
    }

    .am-card-top-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .am-card-left-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .am-dot-sep {
        color: var(--color-text-secondary);
    }

    .am-card-time {
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    .am-card-type-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-secondary);
    }

    /* Badges / Chips */
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

    /* Card Dropdown Menu */
    .am-icon-btn-action {
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .am-icon-btn-action:hover {
        background: var(--color-background-secondary);
        color: var(--color-text-primary);
    }

    .am-dropdown-menu {
        position: absolute;
        top: 30px;
        right: 0;
        width: 150px;
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        z-index: 10;
        padding: 4px 0;
    }

    .am-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: transparent;
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-primary);
        text-align: left;
        cursor: pointer;
    }

    .am-dropdown-item:hover {
        background: var(--color-background-secondary);
    }

    .am-dropdown-item.is-disabled,
    .am-dropdown-item:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .am-dropdown-item.is-disabled:hover,
    .am-dropdown-item:disabled:hover {
        background: transparent;
    }

    .am-dropdown-item i {
        font-size: 14px;
    }

    .am-dropdown-divider {
        height: 0.5px;
        background: var(--color-border-tertiary);
        margin: 4px 0;
    }

    .font-green { color: #3B6D11 !important; }
    .font-red { color: #A32D2D !important; }

    /* Content Row */
    .am-card-content-row {
        margin-top: 8px;
    }

    .am-card-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
    }

    .title-emergency {
        color: #A32D2D;
    }

    .am-card-body {
        font-size: 13px;
        color: var(--color-text-secondary);
        line-height: 1.6;
        margin: 4px 0 0;
    }

    .clamp-lines {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .am-link-show-more {
        background: none;
        border: none;
        color: #003F87;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        padding: 0;
        margin-top: 2px;
    }

    .am-link-show-more:hover {
        text-decoration: underline;
    }

    /* Affected Routes Row */
    .am-card-routes-row {
        margin-top: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .am-card-routes-row i {
        font-size: 14px;
        color: var(--color-text-secondary);
    }

    .am-card-routes-label {
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    .am-card-route-pills {
        display: flex;
        gap: 6px;
    }

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

    /* Action Buttons Row */
    .am-card-action-row {
        margin-top: 10px;
        display: flex;
        gap: 8px;
        align-items: center;
        border-top: 0.5px solid var(--color-border-tertiary);
        padding-top: 10px;
    }

    .am-card-btn-resolve {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 28px;
        padding: 0 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        background: transparent;
        border: 0.5px solid #C0DD97;
        color: #3B6D11;
        transition: background 0.15s;
    }

    .am-card-btn-resolve:hover {
        background: #EAF3DE;
    }

    .am-card-btn-edit {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 28px;
        padding: 0 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        background: transparent;
        border: 0.5px solid var(--color-border-secondary);
        color: var(--color-text-primary);
        transition: background 0.15s;
    }

    .am-card-btn-edit:hover {
        background: var(--color-background-secondary);
    }

    .am-card-btn-broadcast {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        height: 28px;
        padding: 0 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        background: transparent;
        border: 0.5px solid var(--color-border-secondary);
        color: #003F87;
        transition: background 0.15s;
    }

    .am-card-btn-broadcast:hover {
        background: #E6F1FB;
    }

    .am-card-reached-stats {
        margin-left: auto;
        font-size: 12px;
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Collapsible Sections */
    .am-collapsible-section {
        border-top: 0.5px solid var(--color-border-tertiary);
        margin-top: 14px;
    }

    .am-collapsible-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        cursor: pointer;
    }

    .am-collapsible-header-left {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .am-collapsible-header i {
        font-size: 14px;
        color: var(--color-text-secondary);
    }

    .am-collapsible-title {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text-secondary);
    }

    .am-collapsible-link {
        font-size: 12px;
        color: #003F87;
        text-decoration: none;
    }

    .am-collapsible-link:hover {
        text-decoration: underline;
    }

    .am-collapsible-content {
        padding-bottom: 8px;
    }

    /* Resolved List Items */
    .am-resolved-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .am-resolved-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: var(--color-background-secondary);
        border-radius: 8px;
    }

    .am-resolved-dot {
        width: 8px;
        height: 8px;
        background-color: var(--color-text-secondary);
        border-radius: 50%;
        opacity: 0.5;
    }

    .am-resolved-type {
        font-size: 12px;
        color: var(--color-text-secondary);
        font-weight: 500;
        white-space: nowrap;
    }

    .am-resolved-title {
        font-size: 13px;
        color: var(--color-text-secondary);
        text-decoration: line-through;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
    }

    .am-resolved-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .am-resolved-meta {
        font-size: 11px;
        color: var(--color-text-secondary);
        white-space: nowrap;
    }

    .am-resolved-right i {
        color: #3B6D11;
        font-size: 14px;
    }

    .am-resolved-delete {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border: none;
        border-radius: 6px;
        background: transparent;
        cursor: pointer;
    }

    .am-resolved-delete:hover {
        background: #FEECEC;
    }

    .am-resolved-delete i {
        color: #A32D2D;
        font-size: 14px;
    }

    /* Scheduled List Items */
    .am-scheduled-list-wrapper {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .am-scheduled-card {
        background: var(--color-background-primary);
        border: 0.5px dashed #003F87;
        border-radius: 12px;
        padding: 14px 16px;
    }

    .am-sched-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .am-sched-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        background: #E6F1FB;
        color: #0C447C;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 500;
    }

    .am-sched-publish-time {
        font-size: 12px;
        color: var(--color-text-secondary);
        font-weight: 500;
    }

    .am-sched-title {
        font-size: 14px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 8px 0 0;
    }

    .am-sched-body {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin: 4px 0 0;
        line-height: 1.5;
    }

    .am-sched-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        border-top: 0.5px solid var(--color-border-tertiary);
        padding-top: 8px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .am-sched-routes {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .am-sched-routes i {
        font-size: 14px;
        color: var(--color-text-secondary);
    }

    .am-sched-routes-lbl {
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-right: 4px;
    }

    .am-sched-actions {
        display: flex;
        gap: 12px;
    }

    .am-sched-btn-edit {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 500;
        color: #003F87;
        cursor: pointer;
        padding: 0;
    }

    .am-sched-btn-edit:hover {
        text-decoration: underline;
    }

    .am-sched-btn-cancel {
        background: none;
        border: none;
        font-size: 12px;
        font-weight: 500;
        color: #A32D2D;
        cursor: pointer;
        padding: 0;
    }

    .am-sched-btn-cancel:hover {
        text-decoration: underline;
    }

    /* Composer Layout */
    .am-composer-hdr {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .am-composer-hdr-left {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #003F87;
    }

    .am-composer-hdr-left i {
        font-size: 18px;
    }

    .am-composer-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
    }

    .am-link-clear {
        font-size: 12px;
        color: var(--color-text-secondary);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .am-link-clear:hover {
        color: var(--color-text-primary);
    }

    .am-composer-body {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .am-field {
        display: flex;
        flex-direction: column;
    }

    .am-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-secondary);
        margin-bottom: 6px;
    }

    .am-label-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Composer Grid selectors */
    .am-type-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }

    .am-type-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 10px;
        padding: 10px 8px;
        cursor: pointer;
        text-align: center;
        transition: all 0.15s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .am-type-card:hover {
        background: var(--color-background-secondary);
    }

    .am-type-card i {
        font-size: 22px;
        display: block;
        margin-bottom: 4px;
        color: var(--color-text-secondary);
    }

    .am-type-card span {
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
    }

    /* Selected state */
    .am-type-card.selected {
        border: 2px solid #003F87 !important;
        background: #E6F1FB;
    }
    .am-type-card.selected i,
    .am-type-card.selected span {
        color: #0C447C;
    }

    /* Selected Emergency state */
    .am-type-card.selected-emergency {
        border: 2px solid #E24B4A !important;
        background: #FCEBEB;
    }
    .am-type-card.selected-emergency i,
    .am-type-card.selected-emergency span {
        color: #A32D2D;
    }

    /* Segmented pill control */
    .am-sev-pill-row {
        display: flex;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 99px;
        overflow: hidden;
        background: var(--color-background-primary);
    }

    .am-sev-pill {
        flex: 1;
        border: none;
        background: transparent;
        padding: 6px 0;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        text-align: center;
        color: var(--color-text-secondary);
        transition: all 0.15s;
    }

    .am-sev-pill:hover {
        background: var(--color-background-secondary);
    }

    /* active states */
    .am-sev-pill.pill-low.active { background: #E6F1FB; color: #0C447C; }
    .am-sev-pill.pill-medium.active { background: #FAEEDA; color: #854F0B; }
    .am-sev-pill.pill-high.active { background: #FAECE7; color: #7A2000; }
    .am-sev-pill.pill-emergency.active { background: #FCEBEB; color: #A32D2D; }

    .am-emergency-warning-banner {
        background: #FCEBEB;
        color: #A32D2D;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
    }

    .am-emergency-warning-banner i {
        font-size: 14px;
        flex-shrink: 0;
    }

    /* Inputs */
    .am-input {
        width: 100%;
        height: 36px;
        padding: 8px 10px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 13px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        transition: border-color 0.15s;
    }

    .am-input:focus {
        border-color: #003F87;
        box-shadow: 0 0 0 3px rgba(0, 63, 135, 0.20);
    }

    .am-textarea {
        width: 100%;
        padding: 8px 10px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 13px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        resize: none;
        transition: border-color 0.15s;
    }

    .am-textarea:focus {
        border-color: #003F87;
        box-shadow: 0 0 0 3px rgba(0, 63, 135, 0.20);
    }

    .am-char-counter-row {
        display: flex;
        justify-content: flex-end;
        margin-top: 3px;
    }

    .am-char-counter {
        font-size: 12px;
        color: var(--color-text-secondary);
    }

    .counter-amber { color: #BA7517; }
    .counter-red { color: #E24B4A; font-weight: bold; }

    /* Route Pills Row */
    .am-route-pill-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .am-route-pill {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-secondary);
        color: var(--color-text-secondary);
        padding: 5px 12px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }

    .am-route-pill:hover {
        background: var(--color-background-secondary);
    }

    /* Selected state */
    .am-route-pill.selected-a { background: #E6F1FB; color: #0C447C; border-color: #003F87; }
    .am-route-pill.selected-b { background: #EAF3DE; color: #3B6D11; border-color: #3B6D11; }
    .am-route-pill.selected-c { background: #FAEEDA; color: #854F0B; border-color: #854F0B; }
    .am-route-pill.selected-all { background: #003F87; color: #ffffff; border-color: #003F87; }

    /* Checkboxes Target Group */
    .am-checkbox-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .am-checkbox-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        cursor: pointer;
    }

    .am-checkbox-input {
        width: 16px;
        height: 16px;
        margin-top: 2px;
        accent-color: #003F87;
        border-radius: 4px;
        cursor: pointer;
    }

    .am-checkbox-label-block {
        display: flex;
        flex-direction: column;
    }

    .am-checkbox-title {
        font-size: 13px;
        color: var(--color-text-primary);
        font-weight: 500;
    }

    .am-checkbox-subtitle {
        font-size: 11px;
        color: var(--color-text-secondary);
        margin-top: 1px;
    }

    /* Timing controls */
    .am-timing-radio-row {
        display: flex;
        gap: 8px;
    }

    .am-timing-radio-card {
        flex: 1;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-primary);
    }

    .am-timing-radio-card input {
        accent-color: #003F87;
    }

    .am-schedule-time-block {
        background: var(--color-background-secondary);
        padding: 10px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-tertiary);
    }

    .am-helper-text {
        font-size: 11px;
        color: var(--color-text-secondary);
    }

    .am-divider {
        border: none;
        border-top: 0.5px solid var(--color-border-tertiary);
        margin: 6px 0;
    }

    /* Route Suspension toggle row */
    .am-toggle-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 12px 0;
        transition: all 0.15s;
    }

    .am-toggle-row.suspension-active {
        background: #FFF5F5;
        border-radius: 8px;
        padding: 10px 12px;
    }

    .am-toggle-label-block {
        display: flex;
        flex-direction: column;
        flex: 1;
        padding-right: 8px;
    }

    .am-toggle-title {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text-primary);
    }

    .am-toggle-subtitle {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-top: 2px;
        line-height: 1.4;
    }

    .am-toggle-switch {
        padding-top: 4px;
        cursor: pointer;
    }

    .am-toggle-track {
        width: 32px;
        height: 18px;
        background: #D3D1C7;
        border-radius: 99px;
        position: relative;
        transition: background 150ms ease;
    }

    .am-toggle-thumb {
        width: 14px;
        height: 14px;
        background: #ffffff;
        border-radius: 50%;
        position: absolute;
        top: 2px;
        left: 2px;
        transition: transform 150ms ease;
    }

    /* Toggle ON state */
    .am-toggle-on {
        background: #E24B4A !important;
    }

    .am-toggle-on .am-toggle-thumb {
        transform: translateX(14px);
    }

    .am-warning-card {
        background: #FCEBEB;
        border-left: 3px solid #E24B4A;
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        align-items: flex-start;
        gap: 8px;
    }

    /* Composer Footer */
    .am-composer-ftr {
        padding: 14px 16px;
        border-top: 0.5px solid var(--color-border-tertiary);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .am-link-draft {
        font-size: 12px;
        color: var(--color-text-secondary);
        text-decoration: none;
    }

    .am-link-draft:hover {
        text-decoration: underline;
    }

    /* ==================== SCREEN 4: CONFIRMATION OVERLAYS ==================== */
    .am-overlay-wrapper {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        padding: 16px;
        backdrop-blur: 2px;
    }

    .am-confirm-card {
        background: var(--color-background-primary);
        border-radius: 16px;
        border: 0.5px solid var(--color-border-tertiary);
        width: 100%;
        max-width: 440px;
        padding: 24px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }

    .am-confirm-hdr {
        text-align: center;
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .am-confirm-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .confirm-circle-emergency { background: #FCEBEB; color: #A32D2D; }
    .confirm-circle-warning { background: #FAEEDA; color: #854F0B; }
    .confirm-circle-info { background: #E6F1FB; color: #003F87; }
    .confirm-circle-success { background: #EAF3DE; color: #3B6D11; }

    .am-confirm-icon-circle i {
        font-size: 24px;
    }

    .am-confirm-title {
        font-size: 16px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 10px 0 0;
    }

    .am-confirm-subtitle {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin: 4px 0 0;
    }

    .am-confirm-summary-block {
        background: var(--color-background-secondary);
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
    }

    .am-confirm-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        font-size: 13px;
    }

    .am-confirm-lbl {
        color: var(--color-text-secondary);
    }

    .am-confirm-val {
        color: var(--color-text-primary);
        font-weight: 500;
        display: flex;
        gap: 4px;
    }

    .chip-type-display {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        color: var(--color-text-primary);
        font-weight: 500;
    }

    .am-confirm-btns {
        display: flex;
        gap: 8px;
        width: 100%;
    }

    .flex-1 {
        flex: 1;
    }

    /* History overlay view */
    .am-history-overlay-wrapper {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 90;
        padding: 24px;
        backdrop-blur: 2px;
    }

    .am-history-modal-card {
        background: var(--color-background-primary);
        border-radius: 12px;
        border: 0.5px solid var(--color-border-tertiary);
        width: 100%;
        max-width: 1000px;
        height: 100%;
        max-height: 600px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        overflow: hidden;
    }

    .am-history-hdr {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .am-history-hdr-left {
        display: flex;
        flex-direction: column;
    }

    .am-history-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
    }

    .am-history-subtitle {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-top: 1px;
    }

    .am-history-hdr-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .am-icon-btn-close-history {
        width: 28px;
        height: 28px;
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 6px;
        background: transparent;
        color: var(--color-text-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        cursor: pointer;
    }

    .am-icon-btn-close-history:hover {
        background: var(--color-background-secondary);
        color: var(--color-text-primary);
    }

    /* History Filters Row */
    .am-history-filters-row {
        display: flex;
        gap: 12px;
        padding: 10px 16px;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-secondary);
        flex-wrap: wrap;
    }

    .am-hist-filter-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .am-hist-lbl {
        font-size: 12px;
        font-weight: 500;
        color: var(--color-text-secondary);
    }

    .am-select-sm {
        height: 28px;
        padding: 0 8px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 6px;
        font-size: 12px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        cursor: pointer;
    }

    /* History Table */
    .am-history-table-wrapper {
        flex: 1;
        overflow-y: auto;
    }

    .am-history-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .am-table-hdr-row {
        background: var(--color-background-secondary);
        position: sticky;
        top: 0;
        z-index: 5;
    }

    .am-table-th {
        padding: 10px 16px;
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        text-align: left;
    }

    .am-table-row {
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .am-table-row:hover {
        background: #EEF3FF;
    }

    .am-table-cell {
        padding: 10px 16px;
        font-size: 13px;
        color: var(--color-text-primary);
        vertical-align: middle;
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
    .am-history-pagination-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-top: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
    }

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

    /* Animation */
    @keyframes fade-in-up {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in-up {
        animation: fade-in-up 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes am-spin {
        to { transform: rotate(360deg); }
    }

    .am-alert-card-highlight {
        animation: alert-card-highlight 1.8s ease-out forwards;
    }

    @keyframes alert-card-highlight {
        0% { box-shadow: 0 0 0 0 rgba(0, 63, 135, 0.35); }
        30% { box-shadow: 0 0 0 4px rgba(0, 63, 135, 0.24); }
        100% { box-shadow: 0 0 0 0 rgba(0, 63, 135, 0); }
    }

    .mt-1.5 { margin-top: 6px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 12px; }
    .mb-4 { margin-bottom: 16px; }
    .mb-6 { margin-bottom: 24px; }
    .w-full { width: 100%; }
</style>



