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