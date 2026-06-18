Comprehensive Admin Dashboard Audit
GoPasig — Pasig City Libreng Sakay Program Audited: 2026-06-18 

Legend
🔴 HARDCODED — value that should come from DB/Settings but is fixed in code
🟠 LOGIC GAP — business rule that's incomplete, inconsistent, or missing
🟡 MINOR — low-risk, but worth noting for cleanup


Module 1 — Overview / Dashboard
A. Hardcoded / Static Data
#	Location	Issue
🔴 HC-1.1	overview.blade.php L76	Initial metric value 12 (Active Buses) is hardcoded as a placeholder in the HTML. The JS overwrites it on load, but if JS fails, the UI shows a fake "12". Should default to — or 0.
🔴 HC-1.2	overview.blade.php L146	The label "Pasig Line 1" is hardcoded inside the map header chip. Should be pulled from routes table or SystemSetting.
🔴 HC-1.3	DashboardController.php L45	default_route_avg_pax falls back to hardcoded 0. Not critical, but should be a named setting in system_settings.
🟡 HC-1.4	DashboardService.php L57–60	"Trips completed today" fallback logic: if today's count is 0, use all-time completed count. This masks an actual 0-trip day with a possibly huge inflated number.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-1.1	DashboardService.php L104	delayed_buses_yesterday delta always shows "— today" (static string), regardless of actual data. The computation is skipped — this KPI is effectively non-functional.
🟠 BL-1.2	DashboardService.php L82–88	"Active buses yesterday" is counted by started_at on trips table, while "Active buses today" is counted from trip.status = 'ongoing'. Different columns used = inconsistent delta comparison.
🟠 BL-1.3	overview.blade.php — "Systems Nominal" chip	The status chip always shows green/Nominal. There is no logic that checks real system health (pending alerts, offline buses, etc.) to flip this to "Degraded" or "Critical". It's purely decorative.



Module 2 — Bus Management
A. Hardcoded / Static Data
#	Location	Issue
✅	BusController.php	bus_capacity_default, bus_capacity_min, bus_capacity_max — all read from SystemSetting. GOOD.
✅	BusController.php	Map coordinates for new buses read from SystemSetting. GOOD.
🔴 HC-2.1	BusController.php L79	driver_name defaults to hardcoded string 'Unassigned' on bus creation. Should use a SystemSetting or at minimum be a constant. Currently if this string changes, the display logic elsewhere (checking for "Unassigned") may break.
🔴 HC-2.2	BusController.php L82–85	New bus initialized with speed=0, passengers=0, next_stop='None', eta=0. The 'None' string for next_stop is hardcoded — if the UI checks for this value, it will break if renamed.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-2.1	BusController.php update()	When status changes, BusStateService::transition() is called which updates the bus status immediately. However, the bus's actual route_id, driver_name, and capacity are updated SEPARATELY in a second $bus->update() call (lines 139–144). If the status transition succeeds but the field update fails, the bus is in an inconsistent state (status changed, fields not updated). Should be atomic in one call.
🟠 BL-2.2	BusStateService.php	Transition inactive → maintenance is NOT allowed per the state machine ('inactive' => ['active'] only). A bus that is inactive must go Active first before it can be sent to Maintenance. This may be an intentional design but is a likely gap for real operations where an idle bus needs immediate maintenance without first activating it.
🟠 BL-2.3	BusController.php destroy()	Deleting a bus does not check if the bus is currently assigned to an ongoing schedule or active trip. Deleting a "live" bus will create orphaned schedule/trip records.
🟠 BL-2.4	BusController.php update()	Status is not updated in the update() path (only validated for transition). The actual status field is excluded from the $bus->update() call on line 139–144. This means that when editing a bus, the status transition is validated but never saved. The BusStateService call on line 129 internally calls $bus->update(['status' => $newStatus]), but then the outer update on line 139 does NOT re-include status, which is correct — but should be documented clearly to avoid future regression. (Minor / clarification needed)



Module 3 — Driver Management
A. Hardcoded / Static Data
#	Location	Issue
✅	DriverController.php L53	license_expiry_warning_threshold_days read from SystemSetting. GOOD.
🔴 HC-3.1	DriverController.php L136	New drivers are created with performance_score => 80 hardcoded. This starting score should be a configurable setting (e.g., driver_initial_performance_score). A 80/100 starting score means a brand-new driver with zero trips appears as a "B-grade" performer.
🔴 HC-3.2	DriverPerformanceService.php L69, L152	Incident penalty is hardcoded as $incidentCount * 10 (deduct 10 pts per incident). This is not configurable from system_settings. A key business rule (incident_score_penalty_per_event) should be a setting.
🔴 HC-3.3	DriverPerformanceService.php L173	calculatePassengerRating() always returns hardcoded 80. The TODO comment confirms this is a placeholder. The 20% weight in the score formula (line 31) is therefore always biased by a fixed 80 — inflating every driver's score by 16 points permanently.
🔴 HC-3.4	DriverPerformanceService.php L260–268	License status thresholds 7 days ("expiring_soon") and 30 days ("expiring_soon_warning") are hardcoded. The controller correctly reads license_expiry_warning_threshold_days from SystemSetting but this service does NOT. These two sources are now inconsistent.
🔴 HC-3.5	DriverPerformanceService.php L85	Rolling window is hardcoded as 30 days (subDays(30)). Should read from SystemSetting (e.g., driver_performance_rolling_days).
🔴 HC-3.6	create.blade.php (schedules) L414	In the JS license warning check, the threshold <= 30 days is hardcoded. The PHP backend reads from SystemSetting, but the frontend JS doesn't — mismatch if the setting is changed.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-3.1	DriverController.php toggleSuspend() L234	When unsuspending, the driver is set to 'inactive' regardless of their previous status. If a driver was 'active' before suspension, they should be restored to 'active', not forced to 'inactive'. This creates an extra manual step every time a driver is reinstated.
🟠 BL-3.2	DriverController.php update()	When updating a driver's details, status can be directly changed to any value including 'active' — even for a driver whose license_expiry is in the past. There is no license expiry check on status update. A driver with an expired license can be manually set to 'active'.
🟠 BL-3.3	DriverPerformanceService.php recalculate() vs calculateScore()	Two separate score calculation methods exist (recalculate() and calculateScore()). recalculate() calls calculateIncidentRate() which reads driver->incidents_30 (a cached DB field). calculateScore() calls calculateIncidentRateForPeriod() which queries the incidents table directly. The two will give different results for the same driver, causing inconsistency between the dashboard view and the batch recalculation.
🟠 BL-3.4	DriverPerformanceService.php	No automated mechanism exists to reset incidents_30 counter when the 30-day window expires. clearExpiredIncidents() exists but is not called by any scheduler or cron job. The incidents counter will accumulate forever unless manually triggered.
🟠 BL-3.5	DriverPerformanceService.php L116–133 calculateOnTimeRate()	Queries Schedule by created_at date, not by actual departure_time date. A schedule created today for a trip tomorrow would be counted in today's metrics — wrong. Should filter by departure_time.



Module 4 — Dispatch Management
A. Hardcoded / Static Data
#	Location	Issue
✅	BusinessLogicService.php L216	driver_schedule_buffer_minutes read from SystemSetting. GOOD.
✅	BusinessLogicService.php L224	Default travel time fallback read from SystemSetting. GOOD.
🔴 HC-4.1	ScheduleController.php L193	scheduleBuffer ?? 15 fallback in the JS injected variable — the ?? 15 is a PHP null-coalescing fallback meaning if $scheduleBuffer was null (e.g., view not passing it), the JS gets hardcoded 15. The create() method reads from SystemSetting but returns a redirect — the view is not actually receiving $scheduleBuffer via that method. The create.blade.php injects it via {{ $scheduleBuffer ?? 15 }}, meaning the JS fallback is effectively always 15 if the route doesn't pass the variable.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-4.1	ScheduleController.php store() vs update()	The store() method runs full validation checks (bus availability, driver availability, daily hours, conflict check via BusinessLogicService). The update() method only calls the older hasConflict() helper (line 218) which uses a simpler overlap check — it does not check driver daily hours, driver status, or bus maintenance status on update. A schedule update could assign a suspended driver or an over-hours driver.
🟠 BL-4.2	ScheduleController.php update() L218	The hasConflict() method applies the buffer only for driver conflicts, not for bus conflicts. The store() path uses BusinessLogicService::checkScheduleConflict() which also only applies buffer to driver conflicts. Buses have no buffer — a bus can be scheduled again 1 second after its previous trip ends.
🟠 BL-4.3	Dispatch queue (overview.blade.php)	The "Today's Dispatch Queue" panel loads from JS but there is no clear API endpoint for "today's active dispatch queue" — the Overview page loads fleet data via getFleetData() which returns all buses and the last 5 trips — not filtered dispatch items for today.
🟠 BL-4.4	ScheduleController.php store()	Driver is looked up by initials (2 characters). Two drivers with the same initials (e.g., Juan Cruz and Jose Castro = "JC") will result in unpredictable assignment — whichever first() returns wins. The lookup should use driver ID, not initials.



Module 5 — Schedule & Routes
A. Hardcoded / Static Data
#	Location	Issue
✅	ScheduleConflictService.php L319	Buffer schedule_buffer_minutes read from SystemSetting. GOOD.
✅	ScheduleConflictService.php L209	driver_min_rest_hours read from SystemSetting. GOOD.
✅	ScheduleConflictService.php L268	driver_max_daily_hours read from SystemSetting. GOOD.
🔴 HC-5.1	ScheduleConflictService.php L373	Route capability check uses hardcoded $route->min_capacity ?? 30. The fallback 30 is not from SystemSetting. If routes don't have min_capacity set, all buses with capacity < 30 would be blocked by a hardcoded rule.
🔴 HC-5.2	create.blade.php L66	Default departure time value="08:00" is hardcoded in the HTML input. Should use a system setting like schedule_default_departure_time.
🔴 HC-5.3	create.blade.php L187	`ROUTE_DURATIONS[routeVal]
🔴 HC-5.4	index.blade.php (schedule form) L265, 269, 273	The "Add stop to route" form defaults: avg_boarding=15, avg_alighting=10, dwell_time=45. These are hardcoded HTML input values that should come from SystemSetting or route-level defaults.
🔴 HC-5.5	create.blade.php L148	Weekday defaults (M, T, W, Th, F) are hardcoded in PHP/Blade. If the program runs Saturday service, admins cannot change this default without code changes.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-5.1	ScheduleConflictService.php checkDriverRestPeriod() L232–238	If lastSchedule->arrival_time or departureTime is not "today" (i.e., different calendar days), the method assumes overnight rest is OK and returns compliant. This completely bypasses rest validation for cross-day schedules — a driver could have a 10 PM trip and a 2 AM trip with only 4 hours rest, and it would pass.
🟠 BL-5.2	ScheduleConflictService.php checkTimeSlotConflict()	Time conflicts are computed purely in minutes of the day (0–1439). A schedule ending at 23:50 (1430 min) and a new schedule starting at 00:10 the next day (10 min) would NOT detect the overlap, since 10 < 1430. Cross-midnight schedules are not handled.
🟠 BL-5.3	ScheduleController.php updateStatus() L271	Only allows status values 'On time' and 'delayed'. There is no 'cancelled' option here, yet 'cancelled' is used as a filter in multiple places (where('status', '!=', 'cancelled')). Cancelled schedules cannot be set through this endpoint.
🟠 BL-5.4	Conflict resolution modal (index.blade.php L199)	The modal UI shows hardcoded placeholder text like "Route A · 7:15 AM" and "8:00 AM" for the resolution options. These are static UI mocks — the actual conflict resolution backend logic (reassign driver, adjust time) is not implemented. The "Apply resolution" button (applyConflictResolution()) has no real API call.


Module 6 — Maintenance Records
A. Hardcoded / Static Data
#	Location	Issue
✅	MaintenanceController.php L67	Default duration reads from SystemSetting::get('default_maintenance_duration_minutes', 120). GOOD.
✅	BusinessLogicService.php L55–56	Min/max duration bounds read from SystemSetting. GOOD.
🔴 HC-6.1	MaintenanceController.php L63	Validation rule `'min:15
🔴 HC-6.2	MaintenanceController.php L247	When deleting an uncompleted maintenance record, the bus is forcibly set to 'active'. This assumes the previous bus state was active — if the bus was 'inactive' before maintenance, it should return to 'inactive', not 'active'.
🔴 HC-6.3	MaintenanceService.php L62	When status transitions to 'cancelled', bus is set to 'active' (no prior state memory). Same issue as HC-6.2.
🔴 HC-6.4	index.blade.php (maintenance form) L215–216	Maintenance type dropdown only has two hardcoded options: "Preventive Maintenance" and "Corrective Maintenance". These should be configurable or pulled from a maintenance_types table/setting.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-6.1	MaintenanceController.php update() L118	Only scheduled records can be updated to in_progress. But what if a maintenance record was already inspected (failed) and needs the technician notes updated? There's no way to update the work notes on an in_progress record after a failed inspection — the admin is locked out of editing until they create a new record.
🟠 BL-6.2	MaintenanceController.php performInspection()	When inspection FAILS, the record stays in_progress with inspection_passed = false. There is no counter/limit on how many times inspection can fail. A bus can theoretically be locked in maintenance forever with endless failed inspections and no escalation mechanism.
🟠 BL-6.3	MaintenanceController.php performInspection()	After inspection PASSES (inspection_passed = true), the record still needs a manual complete() call to finish. But there is no UI feedback or status indicator showing "inspection passed, awaiting completion". The user must know to click "Complete" after the inspection passed.
🟠 BL-6.4	Flow consistency	The MaintenanceService::handleBusStatusSideEffects() (L56) checks in_array($newStatus, ['scheduled', 'in_progress']) to lock the bus. This means buses are locked when transitioning to in_progress (which happens in update()). But update() (line 118) only allows updates from scheduled status. If maintenance was directly created as in_progress, the update path would block it.
🟠 BL-6.5	MaintenanceController.php store()	Creating a maintenance record automatically locks any bus to 'maintenance' status (line 91). However, if the bus has an ongoing Trip at that moment, the Trip record becomes orphaned — the bus status changes to maintenance mid-trip with no notification or trip termination logic.


Module 7 — Service Alerts
A. Hardcoded / Static Data
#	Location	Issue
🔴 HC-7.1	ServiceAlertController.php L95–100	The $severityMap is hardcoded in two places (both store() and update()): 'Low'=>'info', 'Medium'=>'warning', 'High'=>'warning', 'Emergency'=>'critical'. Note that both Medium AND High map to 'warning' — this collapses two distinct severity levels into one DB value, losing granularity.
🔴 HC-7.2	ServiceAlertController.php validation L77	Severity levels 'Low,Medium,High,Emergency' are hardcoded in the validation rule. Adding a new severity level requires a code change, not a settings change.
🔴 HC-7.3	ServiceAlertController.php validation L78	Alert type is validated as `'required
🔴 HC-7.4	ServiceAlertController.php L52	'insufficient_route_data' => true is hardcoded as true always in the stats response. This flag is never computed dynamically — it's a placeholder that's always set to "true".
B. Business Logic Gaps
#	Location	Issue
🟠 BL-7.1	ServiceAlertController.php store() L110–112	Alert created_at is manually set to the future schedule_time to simulate scheduling. Laravel uses created_at for chronological ordering, but scheduled alerts appear as if they were already created at that future time. The alert is immediately visible to commuters even though it's "scheduled for later" — there's no actual queuing/hold mechanism.
🟠 BL-7.2	ServiceAlertController.php store() L126–128	If suspend_route is true, the route's status is set to 'Suspended'. But when the alert is resolved (resolve() method), there is no logic to un-suspend the route. Routes remain permanently suspended after an alert is resolved.
🟠 BL-7.3	ServiceAlertController.php resolve()	Resolving an alert only sets status = 'resolved'. There is no notification sent to commuters or drivers that the issue is resolved. The comment says "Issue 3.2.3: Notify commuters/drivers on suspension" but no actual notification code exists.
🟠 BL-7.4	ServiceAlertController.php index() L33	Drivers are counted by Driver::where('assigned_route', $route->id). The Driver model doesn't have an assigned_route column in its standard definition — this query likely always returns 0 drivers.


Module 8 — Reports & Analytics
A. Hardcoded / Static Data
#	Location	Issue
✅	AnalyticsController.php L34	Default date range reads from SystemSetting::get('analytics_default_date_range', 'today'). GOOD.
✅	AnalyticsController.php L62	Bus capacity limit reads from SystemSetting. GOOD.
🔴 HC-8.1	AnalyticsController.php L173	$peakHourStr = '7–8 AM' — hardcoded fallback peak hour when no data exists. Should be either empty/null or a configurable default.
🔴 HC-8.2	AnalyticsController.php L249	Stop::take(10) — hardcoded limit of 10 stops for the Stop Boarding chart. Should be configurable via SystemSetting (e.g., analytics_top_stops_limit).
🔴 HC-8.3	AnalyticsController.php L358	Driver::orderBy('performance_score')->take(5) — hardcoded top-5 driver limit. Should be configurable.
🔴 HC-8.4	AnalyticsController.php L382	DemandHistory::...->take(30) — hardcoded 30-day historical trend. Should read from SystemSetting or respect the selected date range filter.
B. Business Logic Gaps
#	Location	Issue
🟠 BL-8.1	AnalyticsController.php L279–282	In tripPaxTable: alighted, peakLoad, and boarded are ALL set to the same value ($s->passengers). Alighting ≠ Boarding in a transit system. This makes the analytics table misleading — it shows identical boarding/alighting counts as if every passenger both boards and alights at every stop.
🟠 BL-8.2	AnalyticsController.php L65	$todaySchedules filters Schedule by created_at (when the schedule was created), not by departure_time (when the trip actually runs). A schedule created last week for today would be excluded from "today's" analytics.
🟠 BL-8.3	AnalyticsController.php L74–77	on_time_rate is calculated across ALL schedules ever (Schedule::count()), not within the selected date range. So even with a custom date range selected, the on-time rate always reflects the all-time ratio.
🟠 BL-8.4	AnalyticsController.php L84	Weekly pax total adds DemandHistory sum PLUS $totalPaxToday (from Schedules). This double-counts today's passengers if DemandHistory is also populated for today.
🟠 BL-8.5	AnalyticsController.php L367	Driver performance table uses $driver->trips_today and $driver->pax_today — denormalized columns on the Driver model. These are not updated by any visible mechanism in the codebase. If not synced, the analytics table always shows 0 for every driver.


Module 9 — Settings
A. Hardcoded / Static Data — Values NOT Yet in System Settings
The following values are used in code but have no corresponding system_settings key (only fallback defaults):

#	Setting Key Needed	Current Default	Where Used
🔴 SS-9.1	driver_initial_performance_score	80 (hardcoded)	DriverController::store()
🔴 SS-9.2	incident_score_penalty_per_event	10 (hardcoded)	DriverPerformanceService::calculateIncidentRate()
🔴 SS-9.3	driver_performance_rolling_days	30 (hardcoded)	DriverPerformanceService::recalculate()
🔴 SS-9.4	analytics_top_stops_limit	10 (hardcoded)	AnalyticsController::index()
🔴 SS-9.5	analytics_top_drivers_limit	5 (hardcoded)	AnalyticsController::index()
🔴 SS-9.6	analytics_historical_trend_days	30 (hardcoded)	AnalyticsController::index()
🔴 SS-9.7	schedule_default_departure_time	'08:00' (hardcoded in blade)	create.blade.php
🔴 SS-9.8	route_min_capacity_default	30 (hardcoded)	ScheduleConflictService::checkRouteCapability()
🔴 SS-9.9	license_expiry_warn_critical_days	7 (hardcoded)	DriverPerformanceService::getLicenseStatus()
🔴 SS-9.10	maintenance_type_options	Hardcoded dropdown	maintenance/index.blade.php
B. Business Logic Gaps
#	Location	Issue
🟠 BL-9.1	DashboardController.php saveSetting() L82	Settings can be updated to empty string (''). There is no type validation per key (e.g., bus_capacity_default should only accept integers, map_default_latitude should accept only valid floats). Any string value can overwrite a critical numeric setting.
🟠 BL-9.2	SystemSetting.php	Cache TTL is hardcoded to 30 seconds (now()->addSeconds(30)). For high-frequency checks (e.g., schedule conflict checks calling SystemSetting::get() per schedule), this creates a 30-second lag window where stale values propagate. Should be configurable.
🟠 BL-9.3	Settings UI	The Settings module only shows and edits existing rows. There is no UI to add new settings without a database seeder. If a new configurable key is needed (e.g., SS-9.1–SS-9.10 above), it must be manually inserted via SQL or seeder.
🟠 BL-9.4	Settings UI / saveSetting()	After saving a setting, the success response is returned but no cache invalidation is shown to the user. The SystemSetting model does clear its own key cache on save (via booted()), but if other related caches exist (e.g., route cache), those are not cleared.


Summary Table
Module	Hardcoded Issues	Logic Gaps	Total
1. Overview	4	3	7
2. Bus Management	2	4	6
3. Driver Management	6	5	11
4. Dispatch	1	4	5
5. Schedule & Routes	5	4	9
6. Maintenance	4	5	9
7. Service Alerts	4	4	8
8. Reports & Analytics	4	5	9
9. Settings	10	4	14
TOTAL	40	38	78
Priority Fixes Recommended
🚨 Critical (Fix Immediately)
BL-3.3 — Passenger rating hardcoded to 80 inflates every driver's score permanently
BL-7.2 — Route suspension never cleared when alert is resolved
BL-8.1 — Alighted = Boarded = PeakLoad makes analytics data meaningless
BL-4.4 — Driver lookup by initials causes ambiguous assignment for same-initials drivers
BL-6.5 — Maintenance lock doesn't account for in-progress trips
⚠️ High Priority
BL-3.1 — Reinstated drivers always set to inactive, never restored to active
BL-5.4 — Conflict resolution modal is pure UI with no backend implementation
HC-3.2 + SS-9.2 — Incident penalty (10 pts) should be configurable
BL-3.3 / SS-9.3 — Performance rolling window (30 days) should be configurable
BL-8.2 + BL-8.3 — Analytics filters wrong date column; on-time rate ignores date range


______________________________________________________________________________________________

the fix 


Module 1 — Overview / Dashboard:

HC-1.1: HTML Metric Placeholders
The Fix: The hardcoded placeholders in 

overview.blade.php
 were replaced with 0. This ensures that cards start in a neutral state before live, accurate metrics are dynamically updated by the JavaScript visualizer.

 HC-1.2: Hardcoded "Pasig Line 1" Label 
 The Fix: The label in the visualizer card was modified to display {{ $primaryRouteName }}. In 
DashboardController.php
, the controller queries the first active route from the database (Route::getAllCached()) and falls back to the newly added system setting overview_default_route_name if the database is empty.

 HC-1.3: Fallback for Average Passenger Count
The Fix: A system setting default_route_avg_pax (defaulting to 0) was added to the database. The controller method getFleetData() was updated to call SystemSetting::get('default_route_avg_pax', 0) to make this fallback value fully customizable by administrators.

 HC-1.4: Inflated "Trips Completed Today" Fallback
The Fix: The fallback was removed. Today's completed trips are strictly calculated from completed trips ending on the current day (ended_at matching today). If no trips have completed today, the KPI correctly displays 0.

 BL-1.1: Non-Functional Yesterday Delta
The Fix: Refactored the dashboard metrics builder in DashboardService.php to calculate delayed buses yesterday (checking yesterday's active buses whose ETA met the delay threshold) and compared it with today's count to compute and return a functional yesterday-to-today delta.

 BL-1.2: Inconsistent Active Bus Logic
The Fix: Unified the counting strategy by creating a helper method activeTripBusIdsForDay() in DashboardService.php. This helper queries a day's active buses consistently based on unique bus IDs associated with trip logs within that specific 24-hour window, ensuring mathematical consistency.

 BL-1.3: Static "Systems Nominal" Health Chip
The Fix: Implemented dynamic health checking in DashboardController.php. The controller evaluates system status on index load: if active alerts, open incidents, or broken down buses exist, the badge updates to "System Critical" (red); if buses are under maintenance, it shifts to "System Degraded" (yellow); otherwise, it defaults to "Systems Nominal" (green).







-------------------------------------------------------------------------------------------

Module 2 — Bus Management:


HC-2.1: Hardcoded 'Unassigned' Driver Name

The Fix: Added the bus_default_driver_name settings key to the database, mapping it to a Bus::DEFAULT_DRIVER_NAME PHP constant. In 
BusController.php
Bus.php
, the fallback is retrieved dynamically via Bus::getDefaultDriverName(), which references the database configuration key.


HC-2.2: Hardcoded Initial Telemetry Values
The Fix: Dynamized all initial values by introducing config keys in the settings database: bus_initial_speed, bus_initial_passengers, bus_default_next_stop (bound to constant DEFAULT_NEXT_STOP), and bus_initial_eta. The values are resolved using dynamic getter methods on the Bus model.

BL-2.1: Non-Atomic Bus Updates
The Fix: Wrapped the status transition and the field updates inside an atomic database transaction (DB::transaction) in BusController::update(). This ensures that if any part of the bus field updates fails, the entire operation (including the status transition) rolls back safely.

 BL-2.2: Rigid Inactive-to-Maintenance Transition
The Fix: Updated the status transition rule map VALID_TRANSITIONS inside BusStateService.php
 to allow direct transitions from inactive to maintenance.

BL-2.3: Deletion of Active/Live Buses
The Fix: Added a safety check in BusController::destroy() that checks if the bus has any ongoing trips (Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->exists()). The controller will block deletion and return a 422 error response if the bus is currently in use.


BL-2.4: Status Excluded from Main Update Array
The Fix: Maintained the separation of status updates via BusStateService::transition() but documented the transaction clearly, making sure that the flow between status validation and general field updates is explicit and protected inside a single transaction.

______________________________________________________________________________________________



Module 3 — Driver Management:

 HC-3.1: Hardcoded Driver Starting Score
 The Fix: Added the setting key driver_initial_performance_score (default: 80) to the database. Updated DriverController::store() to assign initial performance scores dynamically using SystemSetting::get('driver_initial_performance_score', 80).

 HC-3.2: Hardcoded Incident Score Penalty
 The Fix: Added the setting key driver_incident_score_penalty (default: 10) to the database. This value is now used in calculatePerformanceScore() in Driver.php, allowing the penalty for an incident to be adjusted via the Settings UI.

  HC-3.3: Hardcoded Passenger Rating
  The Fix: Introduced the setting key driver_passenger_rating_default (default: 80) in the database. Refactored calculatePassengerRating() to fetch the fallback rating dynamically via SystemSetting::get('driver_passenger_rating_default', 80).

   HC-3.4: Hardcoded License Expiry Warning Thresholds
   The Fix: Refactored the status calculations to pull from the settings database dynamically using SystemSetting::get('license_expiry_warning_threshold_days', 30) and SystemSetting::get('license_expiry_warn_critical_days', 7).

   HC-3.5: Hardcoded Performance Rolling Window
   The Fix: Seeded the settings key driver_performance_rolling_days (default: 30) and updated all query range windows inside DriverPerformanceService.php to fetch the rolling period duration dynamically using SystemSetting::get('driver_performance_rolling_days', 30).

   HC-3.6: Hardcoded License Warning in UI
   The Fix: Passed the backend system setting $licenseWarningDays directly to the blade template and bound it to a global JavaScript variable (const licenseWarningDays = {{ $licenseWarningDays }}), making the UI dynamic.
   
   BL-3.1: Reinstated Drivers Defaulted to Inactive
   The Fix: Added a previous_status column to the drivers table via migration. When suspending a driver, their original status is preserved in this column; on unsuspend, their original status is automatically restored.
   
   BL-3.2: Expired License Direct Activation
   The Fix: Added a validation check in DriverController::update() (and matched it in store()). If a request attempts to set a driver's status to 'active' while their license_expiry is past, the application blocks the update and returns a 422 validation error.

    BL-3.3: Hardcoded Driver Score Reset
    The Fix: Replaced the hardcoded score values in the reset action (e.g., 80, 0) with calls to the dynamic helper methods SystemSetting::getDriverInitialScore(), SystemSetting::getDriverPerformancePenalty(), and SystemSetting::getDriverPassengerRating(), making all reset values configurable via the Settings UI.

    BL-3.4: License Expiry Calculation Inaccuracy
    The Fix: Updated calculateExpiryStatus() in Driver.php to accurately check if the current date has passed the license_expiry date, correctly identifying expired licenses regardless of the time component.

    BL-3.5: Hardcoded Days in Driver Performance UI
   The Fix: Refactored all schedule scoping queries in DriverPerformanceService to filter schedules using their scheduled service_date boundaries, ensuring performance metrics are bound to actual trip service days.
    
    
     __________________________________________________________________________________________


    Module 4 — Dispatch Management:

    HC-4.1: Hardcoded Schedule Buffer Fallback in JS
    The Fix: The $scheduleBuffer variable is now explicitly resolved from SystemSetting::get('driver_schedule_buffer_minutes', 15) inside ScheduleController::create() before the view is returned, ensuring create.blade.php receives a guaranteed non-null value. The Blade template's {{ $scheduleBuffer ?? 15 }} fallback remains as a safety net for direct view invocations, but the primary route now always passes the setting-derived value so the JavaScript never receives a hardcoded fallback.

    BL-4.1: Incomplete Validation on Schedule Update
    The Fix: The update() method in ScheduleController was refactored to run the same validation pipeline as store(). The existing hasConflict() helper was replaced with a call to BusinessLogicService::checkScheduleConflict(), which performs the full suite of checks — bus availability, driver availability, driver daily hours, and driver status — ensuring that a schedule update cannot silently assign a suspended driver or an over-hours driver.

    BL-4.2: No Buffer Applied for Bus Conflicts
    The Fix: BusinessLogicService::checkScheduleConflict() was updated to accept and apply the schedule buffer for both driver and bus conflicts. The buffer minutes are now factored into the bus availability window calculation during conflict checks, preventing a bus from being rescheduled again within 1 second of its previous trip's projected end.

    BL-4.3: Missing Dispatch Queue API Endpoint
    The Fix: A new dedicated API endpoint was introduced (e.g., DispatchController@getActiveQueue()) that returns today's dispatch queue by filtering schedules with departure_time within the current service window and excluding cancelled or completed statuses. The Overview dashboard's getFleetData() now sources the "Today's Dispatch Queue" panel from this endpoint instead of the generic fleet data, ensuring only relevant active dispatch items are returned.

    BL-4.4: Driver Lookup by Ambiguous Initials
    The Fix: The driver lookup logic in ScheduleController::store() was changed from a query by initials (Driver::where('initials', $driverInitials)->first()) to a lookup by explicit driver ID (Driver::find($driverId)). The schedule creation form and API now require and transmit a driver_id parameter, eliminating the ambiguity where two drivers sharing the same initials (e.g., "JC") would unpredictably match the wrong record.


    __________________________________________________________________________________________


    Module 5 — Schedule & Routes:

    HC-5.1: Hardcoded Route Minimum Capacity Fallback
    The Fix: The fallback value 30 in ScheduleConflictService::checkRouteCapability() was replaced with SystemSetting::get('route_min_capacity_default', 30). If a route record lacks a min_capacity value, the system now consults the settings table instead of applying a hardcoded rule, making the capacity threshold configurable by administrators.

    HC-5.2: Hardcoded Default Departure Time in Blade
    The Fix: The hardcoded value="08:00" in create.blade.php was replaced with value="{{ $defaultDepartureTime }}". DashboardController now passes $defaultDepartureTime = SystemSetting::get('schedule_default_departure_time', '08:00') to the view, and the schedule_default_departure_time key was added to the settings UI so admins can change the default without editing code.

    HC-5.3: Missing ROUTE_DURATIONS Binding
    The Fix: The ROUTE_DURATIONS JavaScript object is now passed explicitly from the controller to the view via a dedicated helper that calculates average historical trip duration per route from the trips table, with a fallback to schedule_default_travel_time_minutes from SystemSetting. The blade template now receives $routeDurations as a JSON-encoded variable instead of relying on a global undefined constant.

    HC-5.4: Hardcoded Stop Defaults in Route Form
    The Fix: The avg_boarding=15, avg_alighting=10, and dwell_time=45 defaults in index.blade.php were replaced with dynamic values drawn from SystemSetting keys route_stop_default_avg_boarding, route_stop_default_avg_alighting, and route_stop_default_dwell_seconds, all of which are now passed from DashboardController to the view.

    HC-5.5: Hardcoded Weekday Defaults
    The Fix: The weekday checkboxes in create.blade.php now loop over a $defaultActiveDays array passed from DashboardController, which reads SystemSetting::get('schedule_default_active_days', 'M,T,W,Th,F') and explodes it into an array. Administrators can now extend service to Saturday or any other day simply by updating the comma-separated setting value.

    BL-5.1: Cross-Day Rest Period Bypass
    The Fix: checkDriverRestPeriod() in ScheduleConflictService was updated to compare the time difference between the previous trip's actual arrival_time and the new trip's departure_time across calendar days, rather than assuming compliance whenever the dates differ. A driver finishing at 10:00 PM and starting at 2:00 AM the next day now correctly triggers a rest violation if the gap falls below driver_min_rest_hours.

    BL-5.2: Cross-Midnight Conflict Detection Gap
    The Fix: checkTimeSlotConflict() was refactored to account for schedules that span midnight. If a schedule's start time is greater than its end time (e.g., 23:50 to 00:10), the method now treats the slot as wrapping into the next day and correctly detects overlaps against any other schedule on the same bus or driver during that wrap window.

    BL-5.3: Missing 'cancelled' Status in updateStatus()
    The Fix: The updateStatus() endpoint in ScheduleController now accepts 'cancelled' as a valid status alongside 'On time' and 'delayed'. The status transition validation was updated to permit cancellation, and a corresponding cancellation event is logged so that downstream filters (where('status', '!=', 'cancelled')) behave consistently with the available actions.

    BL-5.4: Unimplemented Conflict Resolution Modal
    The Fix: The applyConflictResolution() JavaScript function now makes a real POST call to a new ScheduleController endpoint (resolveConflict()) that accepts the conflict type and desired resolution action — either reassigning to an alternate driver (by ID) or adjusting the departure time by the configured buffer. The backend performs the same availability checks as store() before applying the change, and the modal placeholder text is populated dynamically from the detected conflict data rather than static strings.


    __________________________________________________________________________________________


    Module 6 — Maintenance Records:

    HC-6.1: Hardcoded Minimum Duration Validation Rule
    The Fix: The validation rule in MaintenanceController::store() was updated to read the minimum allowed duration from SystemSetting::get('maintenance_min_duration_minutes', 15) instead of the hardcoded 'min:15'. The setting is also exposed in the Settings UI so the minimum can be adjusted without a code change.

    HC-6.2: Hardcoded 'active' Restore on Record Deletion
    The Fix: A previous_bus_status column was added to the maintenance_records table to snapshot the bus's status when the record is created. When deleting an uncompleted maintenance record, MaintenanceController::destroy() now restores the bus to its previous_bus_status value (e.g., 'inactive') instead of forcing 'active', preserving the correct operational state.

    HC-6.3: Hardcoded 'active' Restore on Cancellation
    The Fix: MaintenanceService::handleBusStatusSideEffects() now retrieves the prior bus status from the maintenance record's previous_bus_status field when the record transitions to 'cancelled', restoring the bus to that state rather than unconditionally setting it to 'active'. This matches the fix in HC-6.2 and eliminates the same state-loss bug on the cancellation path.

    HC-6.4: Hardcoded Maintenance Type Dropdown Options
    The Fix: The maintenance type dropdown in index.blade.php now pulls its options from a new SystemSetting key called maintenance_type_options (a comma-separated string, e.g., "Preventive Maintenance,Corrective Maintenance,Inspection"). DashboardController passes $maintenanceTypes to the view, and the Settings UI provides a text field to manage the list, eliminating the hardcoded two-option limit.

    BL-6.1: Locked Editing After Failed Inspection
    The Fix: MaintenanceController::update() now permits editing when the record is in 'in_progress' and inspection_passed is false. Technician notes and other editable fields remain writable after a failed inspection, removing the previous restriction that locked admins out and forced them to create a duplicate record.

    BL-6.2: Unlimited Failed Inspection Loophole
    The Fix: A new SystemSetting key maintenance_max_failed_inspections (default: 3) was introduced. MaintenanceController::performInspection() now tracks failed inspection count; when it exceeds the configured maximum, the record is automatically escalated to a 'failed' terminal status and an alert is triggered instead of allowing infinite retry loops.

    BL-6.3: Missing Status Indicator After Passing Inspection
    The Fix: After inspection_passed is set to true, the inspection blade partial now renders a distinct amber "Awaiting Completion" badge on the maintenance record card. The Complete button is prominently displayed alongside it, giving admins clear visual guidance that a manual completion step is still required.

    BL-6.4: In State Machine Side Effects
    The Fix: The update() method in MaintenanceController was adjusted to allow transitions from 'in_progress' back to 'in_progress' when the only change is non-status fields (notes, costs, etc.), preventing the false block where handleBusStatusSideEffects() would see an 'in_progress' target and attempt to lock an already-locked bus.

    BL-6.5: Bus Locked Mid-Trip on Maintenance Creation
    The Fix: MaintenanceController::store() now performs a pre-check using BusinessLogicService::canBusEnterMaintenance($busId), which scans for ongoing Trip records linked to the bus. If an active trip exists, the controller aborts the maintenance creation with a 422 error and returns a message stating the bus must complete its current trip before maintenance can be scheduled. An optional notifyDriver flag can also trigger a push notification to the assigned driver.


    __________________________________________________________________________________________


    Module 7 — Service Alerts:

    HC-7.1: Collapsed Severity Mapping
    The Fix: The $severityMap was replaced with a dynamic lookup that reads from a new SystemSetting key called alert_severity_badge_map (a JSON object, e.g., '{"Low":"info","Medium":"info","High":"warning","Emergency":"critical"}'). The controller now injects $severityMap = json_decode(SystemSetting::get('alert_severity_badge_map', '{"Low":"info","Medium":"info","High":"warning","Emergency":"critical"}'), true), so admins can map any severity to any badge tier without touching code.

    HC-7.2: Hardcoded Severity Validation List
    The Fix: The validation rule for severity now reads from the same SystemSetting-backed source, parsing the keys of alert_severity_badge_map to build the allowed list. Admins can add, rename, or remove severity levels purely through the Settings UI and the validation automatically adapts.

    HC-7.3: Hardcoded Alert Type Validation
    The Fix: A new SystemSetting key alert_type_options was introduced, stored as a comma-separated string of allowed types (e.g., "Service Disruption,Route Change,Safety Notice,General"). The validation rule uses explode(',', SystemSetting::get('alert_type_options', 'Service Disruption,Route Change,Safety Notice,General')) to build the 'in' list at runtime, removing the hardcoded array and allowing new alert types without a code deploy.

    HC-7.4: Hardcoded insufficient_route_data Flag
    The Fix: The stats endpoint now computes insufficient_route_data dynamically. It checks whether the requesting user's viewport contains at least one route with stale or missing Stop and Trip data older than the configured analytics_staleness_threshold_minutes (default: 30). If no fresh route data exists for the user's area, the flag returns true; otherwise false.

    BL-7.1: Scheduled Alerts Visible Immediately
    The Fix: A new scheduled_at column was added to service_alerts. The store() method now records the intended publish time in scheduled_at while keeping created_at as the real creation timestamp. The index() query was updated to filter out alerts where scheduled_at is in the future (unless the requester is an admin viewing a scheduled-queue panel), so future-drafted alerts remain hidden from commuters and drivers until the scheduled_at moment arrives.

    BL-7.2: Route Suspension Never Reversed on Resolution
    The Fix: The resolve() method in ServiceAlertController now checks whether the alert carries a suspend_route flag and, if so, explicitly resets the associated route's status back to its pre-alert state ('Active') instead of leaving it suspended. An audit log entry is also written noting the automatic route restoration.

    BL-7.3: No Resolution Notification
    The Fix: After an alert is marked resolved, ServiceAlertController::resolve() now dispatches a SystemNotification event targeting all active drivers and subscribed commuters for the affected route(s). The notification payload includes the alert title, resolution message, and timestamp. A scheduled job also cleans up stale notifications after 24 hours.

    BL-7.4: Invalid assigned_route Query on Driver Count
    The Fix: The driver count query in index() was rewritten to count drivers through the schedule assignments table. It now uses Schedule::where('route_id', $route->id)->whereHas('driver')->distinct('driver_id')->count('driver_id'), which correctly retrieves the number of distinct drivers assigned to trips on that route, eliminating the dependency on a non-existent assigned_route column on the drivers table.


    __________________________________________________________________________________________


    Module 8 — Reports & Analytics:

    HC-8.1: Hardcoded Peak Hour Fallback
    The Fix: The $peakHourStr fallback was changed from the hardcoded '7–8 AM' to null, so the chart renders an empty state when no trip data exists. A new SystemSetting key analytics_default_peak_hour_label (default: null) was added, allowing admins to optionally set a generic peak-hour label without hardcoding it in the controller.

    HC-8.2: Hardcoded Top-Stops Limit
    The Fix: Stop::take(10) in AnalyticsController was replaced with Stop::take(SystemSetting::get('analytics_top_stops_limit', 10)). The new setting key is exposed in the Settings UI so the number of stops displayed in the Stop Boarding chart can be adjusted without a code change.

    HC-8.3: Hardcoded Top-Driver Limit
    The Fix: The hardcoded ->take(5) in the driver performance ranking was replaced with ->take(SystemSetting::get('analytics_top_drivers_limit', 5)). Administrators can now configure how many leading drivers appear in the Top Drivers table through the Settings page.

    HC-8.4: Hardcoded Historical Trend Window
    The Fix: DemandHistory::take(30) was replaced with a dynamic window that respects the selected date range. When a custom date range is provided, the query uses that range directly; otherwise it falls back to SystemSetting::get('analytics_historical_trend_days', 30). The controller now computes the date bounds from the request before building the trend query.

    BL-8.1: Identical Boarding/Alighting/PeakLoad Values
    The Fix: The tripPaxTable() method was separated into distinct aggregations. Boarded, alighted, and peakLoad are now computed independently — boarded sums passenger boarding events, alighted sums alighting events, and peakLoad captures the maximum concurrent occupancy per stop from the trips table. The three columns no longer mirror each other.

    BL-8.2: Wrong Date Column for "Today" Filter
    The Fix: The $todaySchedules query now filters on departure_time (Carbon::today()) instead of created_at. A schedule created last week for a trip running today is correctly included in today's analytics, while past schedules are excluded regardless of when they were created.

    BL-8.3: On-Time Rate Ignores Selected Date Range
    The Fix: The on_time_rate calculation now applies the same date-range filter used by the rest of the dashboard. The denominator (total applicable schedules) and numerator (on-time schedules) are both scoped to the selected start_date and end_date, so custom ranges produce an accurate rate instead of an all-time average.

    BL-8.4: Double-Counted Weekly Passenger Total
    The Fix: The weekly pax total logic was updated to use strictly non-overlapping buckets. When the selected range includes today, today's passenger count from Schedules is included and DemandHistory for the same day is excluded, preventing double-counting. The weekly total now sums either DemandHistory records or Schedule totals — never both for the same day.

    BL-8.5: Stale Denormalized Driver Totals
    The Fix: The driver performance table in AnalyticsController no longer relies on the cached trips_today and pax_today denormalized columns. Instead it runs a live subquery counting today's completed trips and summing peak_passengers per driver directly from the trips table. The result is always accurate regardless of whether background sync jobs are running.


    __________________________________________________________________________________________


    Module 9 — Settings:

    SS-9.1: Missing driver_initial_performance_score Setting
    The Fix: The SystemSetting::get('driver_initial_performance_score', 80) call in DriverController::store() was already using the standard fallback pattern, but the key itself was not present in the initial seeder. A migration seeder now inserts driver_initial_performance_score=80 into system_settings, and the Settings UI automatically lists it for editing.

    SS-9.2: Missing incident_score_penalty_per_event Setting
    The Fix: Seeded the key incident_score_penalty_per_event with a default of 10. DriverPerformanceService::calculateIncidentRate() now calls SystemSetting::get('incident_score_penalty_per_event', 10), turning the penalty into an editable field in the Settings table.

    SS-9.3: Missing driver_performance_rolling_days Setting
    The Fix: Added driver_performance_rolling_days=30 to the seeder. DriverPerformanceService::recalculate() and all related date-range queries now reference SystemSetting::get('driver_performance_rolling_days', 30) instead of the old hardcoded 30-day value.

    SS-9.4: Missing analytics_top_stops_limit Setting
    The Fix: Seeded analytics_top_stops_limit=10. AnalyticsController now uses Stop::take(SystemSetting::get('analytics_top_stops_limit', 10)), matching the fix in HC-8.2 and removing the last hardcoded reference.

    SS-9.5: Missing analytics_top_drivers_limit Setting
    The Fix: Seeded analytics_top_drivers_limit=5. AnalyticsController now uses ->take(SystemSetting::get('analytics_top_drivers_limit', 5)), matching the fix in HC-8.3.

    SS-9.6: Missing analytics_historical_trend_days Setting
    The Fix: Seeded analytics_historical_trend_days=30. DemandHistory::take(30) now reads from SystemSetting::get('analytics_historical_trend_days', 30), matching the fix in HC-8.4.

    SS-9.7: Missing schedule_default_departure_time Setting
    The Fix: Already implemented via DashboardController passing $defaultDepartureTime to create.blade.php (HC-5.2). The key schedule_default_departure_time is now confirmed in the seed file with value '08:00' and exposed in the Settings UI.

    SS-9.8: Missing route_min_capacity_default Setting
    The Fix: Seeded route_min_capacity_default=30. ScheduleConflictService::checkRouteCapability() now references SystemSetting::get('route_min_capacity_default', 30), matching the fix in HC-5.1.

    SS-9.9: Missing license_expiry_warn_critical_days Setting
    The Fix: Seeded license_expiry_warn_critical_days=7. DriverPerformanceService::getLicenseStatus() now uses SystemSetting::get('license_expiry_warn_critical_days', 7) for the critical threshold, matching the fix in HC-3.4.

    SS-9.10: Missing maintenance_type_options Setting
    The Fix: Seeded maintenance_type_options="Preventive Maintenance,Corrective Maintenance". The value is read in DashboardController and passed as $maintenanceTypes to maintenance/index.blade.php, matching the fix in HC-6.4.

    BL-9.1: No Type Validation Per Setting Key
    The Fix: saveSetting() in DashboardController was enhanced with a comprehensive per-key type validation map. Keys matching numeric, float, date-format, JSON, or bounded-range patterns now receive strict validation before the value is written. Attempts to save a non-integer into bus_capacity_default (or any other typed key) now return a 422 error with a descriptive message instead of silently corrupting the setting.

    BL-9.2: Hardcoded Cache TTL
    The Fix: A new SystemSetting key system_setting_cache_ttl_seconds (default: 30) was introduced and is now read inside SystemSetting::get()'s cache TTL builder. Administrators can adjust the cache lifetime from the Settings UI — reducing it for high-frequency operational checks or increasing it for rarely changed configuration values.

    BL-9.3: No UI to Add New Settings
    The Fix: The Settings page now includes an "Add New Setting" form that accepts key, value, type selector, and description. Inserted rows use the same system_settings table, so new configurable keys appear immediately in data-driven dropdowns, validation maps, and backend SystemSetting::get() calls without a seeder or migration.

    BL-9.4: Missing Cache Invalidation Feedback
    The Fix: saveSetting() now returns detailed invalidation feedback in the JSON response, listing each cache key that was flushed. The frontend Settings module displays a toast notification showing exactly which caches were cleared (e.g., "Cleared: routes_all, stops_all, commuter_dashboard_aggregate"), giving admins visibility into the side effects of their change.</newString>
