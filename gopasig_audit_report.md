# 🔍 GoPasig System — Comprehensive Code Audit Report
 **Scope:** Controllers, Services, Models, Views, Public JS

---
## MANDY PART ##
## START OF MANDY 
28/06/2026
##

## END OF MANDY PART 
2/07/2026
##

## MODULE 1 — Driver Performance Service

### 📁 `app/Services/DriverPerformanceService.php`  -  DONE ✅

---

#### ISSUE-001 
| Field | Detail |
|---|---|
| **File & Line** | [`DriverPerformanceService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/DriverPerformanceService.php#L186-L198) — Lines 186–198 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🔴 Critical |
| **Description** | `calculatePassengerRating()` returns a **hardcoded `80`** unconditionally. It is explicitly commented as "TODO: Integrate with actual passenger rating system." This means 20% of the driver performance score (`$passengerRating * 0.20`) is **fabricated data**, not derived from real passenger feedback. |
| **Affected Formula** | `$performanceScore = (onTimeRate * 0.50) + (incidentRate * 0.30) + (passengerRating * 0.20)` |
| **Suggested Fix** | Create a `PassengerRating` model and table. Aggregate real ratings per driver from the `passenger_ratings` table. Until the table exists, use `SystemSetting::get('driver_default_passenger_score', 80)` so it's configurable and clearly labeled. |

---

#### ISSUE-002
| Field | Detail |
|---|---|
| **File & Line** | [`DriverPerformanceService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/DriverPerformanceService.php#L86-L123) — Lines 86–123 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🔴 Critical |
| **Description** | `recalculate()` uses **`debug_backtrace()`** to detect if it's being called from a test class (`DriverPerformanceScoreTest`) and switches to a **different scoring formula** when called from tests. This is a critical anti-pattern: production code must never alter its business logic based on the call stack. The test and production formulas are different — the test formula uses a penalty model (subtract points), while production uses a weighted score model. |
| **Suggested Fix** | Delete the `debug_backtrace` block entirely. Pick **one canonical formula** (either penalty or weighted) and use it everywhere — in both production and tests. Update unit tests to align with the single formula. |

---

#### ISSUE-003
| Field | Detail |
|---|---|
| **File & Line** | [`DriverPerformanceService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/DriverPerformanceService.php#L141-L157) — Lines 141–157 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | `calculateOnTimeRate()` defaults to **`return 100`** when a driver has no schedule history (`$totalSchedules === 0`). A brand-new driver with zero trips is not the same as a perfect performer. This inflates performance scores for inactive drivers. |
| **Suggested Fix** | Return a configurable neutral default: `SystemSetting::get('driver_default_on_time_score', 75)`. Or simply return `null` and exclude drivers with no data from ranking reports. |

---

#### ISSUE-004
| Field | Detail |
|---|---|
| **File & Line** | [`DriverPerformanceService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/DriverPerformanceService.php#L164-L179) — Lines 164–179 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | `calculateIncidentRate()` uses the formula `max(0, 100 - (incidentCount * 10))`. The penalty per incident (`10`) is **hardcoded in the formula**. Though a similar value exists in `SystemSetting` (`driver_score_incident_penalty`), the setting is **only used inside the test branch** (Issue-002), not in the production code path. |
| **Suggested Fix** | Replace `* 10` with `(int) SystemSetting::get('driver_score_incident_penalty', 10)` in the production path as well. |

---

## MODULE 2 — Report Generation Service

### 📁 `app/Services/ReportGenerationService.php` -  DONE ✅

---

#### ISSUE-005
| Field | Detail |
|---|---|
| **File & Line** | [`ReportGenerationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ReportGenerationService.php#L100-L106) — Lines 100–106 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟠 High |
| **Description** | In `generateRoutePerformanceReport()`, `scheduled_hours` is computed as `$totalSchedules * 1.5` — a hardcoded **estimate** based on the assumption that every schedule is 1.5 hours. This is not derived from actual route `travel_time_minutes` values or from real trip log durations. The comment in the code explicitly acknowledges it: `// Estimate`. |
| **Suggested Fix** | Compute actual scheduled hours by summing `Route::find($route_id)->travel_time_minutes / 60` for each schedule, or sum TripLog `trip_duration_minutes`. |

---

#### ISSUE-006
| Field | Detail |
|---|---|
| **File & Line** | [`ReportGenerationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ReportGenerationService.php#L172-L178) — Lines 172–178 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | `calculateDriverRankingScore()` uses a hardcoded safety penalty of `* 10` per incident and static weights `0.4 / 0.4 / 0.2` for performance, on-time rate, and safety score. These are identical hardcoded weights in *two separate places* (`ReportGenerationService` and `DriverPerformanceService`), creating risk of divergence. |
| **Suggested Fix** | Extract weights to `SystemSetting` keys: `report_score_weight_performance`, `report_score_weight_on_time`, `report_score_weight_safety`. Centralize the formula in `DriverPerformanceService`. |

---

## MODULE 3 — Route Performance Controller (Fleet)

### 📁 `app/Http/Controllers/Fleet/RoutePerformanceController.php`  -  DONE ✅

---

#### ISSUE-007
| Field | Detail |
|---|---|
| **File & Line** | [`RoutePerformanceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/RoutePerformanceController.php#L296-L301) — Line 298 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🔴 Critical |
| **Description** | In `getRoutePerformanceData()`, when a schedule's `delay_minutes` is null and the status is `'delayed'`, the variance is computed as `rand(3, 12)` — a **random number** used as real delay data. This means charts and CSV exports contain **fabricated delay values**. Any delayed trip without a recorded `delay_minutes` value gets a random 3–12 minute delay assigned. |
| **Suggested Fix** | Use `0` when `delay_minutes` is null, or prompt users to record actual delay: `$s->delay_minutes ?? 0`. Do not use `rand()` for business analytics data. |

---

#### ISSUE-008
| Field | Detail |
|---|---|
| **File & Line** | [`RoutePerformanceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/RoutePerformanceController.php#L343-L346) — Lines 343–346 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟡 Medium |
| **Description** | In the stop adherence log, `variance_minutes` and `avg_dwell_seconds` are **hardcoded to `0`** for every stop. These fields appear in the exported CSV but contain no real data — there is no actual tracking of per-stop arrival variance or bus dwell time. The UI shows these columns as if they contain real measurements. |
| **Suggested Fix** | If actual stop arrival data is not yet tracked, either remove these columns from the report/UI, or add a `stop_events` table that records arrival/departure timestamps per stop per trip. |

---

#### ISSUE-009
| Field | Detail |
|---|---|
| **File & Line** | [`RoutePerformanceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/RoutePerformanceController.php#L265) — Line 265 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | `$targetHeadway = $routeForHeadway?->target_headway_minutes ?? 15` — the fallback headway target of `15` minutes is hardcoded inline. The same pattern appears 3 times across the file (lines 234, 239, 265). While two instances correctly use `SystemSetting::get('default_headway_target', 15)`, line 265 does not, causing inconsistency. |
| **Suggested Fix** | Use `SystemSetting::get('default_headway_target', 15)` consistently on line 265 to match the pattern used for `default_on_time_target`. |

---

## MODULE 4 — Schedule Compliance Controller (Fleet)

### 📁 `app/Http/Controllers/Fleet/ScheduleComplianceController.php` - DONE ✅

---

#### ISSUE-010
| Field | Detail |
|---|---|
| **File & Line** | [`ScheduleComplianceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/ScheduleComplianceController.php#L259-L263) — Lines 259–263 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🔴 Critical |
| **Description** | When a delayed schedule has no `actual_departure_time` and no `delay_minutes` value, variance is estimated as `max(1, (int) round($duration * 0.1))` — **10% of trip duration as a synthetic fallback**. For a 60-minute route, this inserts a fake 6-minute delay. This fabricated value is written into the Trip Log, exported to CSV, and shown to fleet managers as factual data. The same formula appears in the most-delayed-routes calculation (line 379) and average delay calculation (line 433). |
| **Suggested Fix** | When actual delay data is unavailable, mark the delay as `null` or `unknown`, and show `—` in the UI. Do not inject estimated delay values into analytical reports. Add a `delay_minutes` field population mechanism when `status` is updated to `'delayed'`. |

---

#### ISSUE-011
| Field | Detail |
|---|---|
| **File & Line** | [`ScheduleComplianceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/ScheduleComplianceController.php#L222-L228) — Lines 222–228 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | The status filter maps `'Early'` to the database value `'On time'` — meaning early departures and on-time departures are treated identically. There is no `'early'` status in the `schedules` table, so the concept of an "early departure" is a logic gap. Early departures can be as problematic as late ones from a transit compliance perspective. |
| **Suggested Fix** | Add an `'early'` status to the `schedules` table enum. Populate `actual_departure_time` when drivers depart. Compute variance; if negative (departed before scheduled time), set status to `'early'`. |

---

## MODULE 5 — Dispatch Intelligence Controller (Fleet)

### 📁 `app/Http/Controllers/Fleet/DispatchIntelligenceController.php`  -  DONE ✅

---

#### ISSUE-012
| Field | Detail |
|---|---|
| **File & Line** | [`DispatchIntelligenceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/DispatchIntelligenceController.php#L251-L273) — Lines 251–273 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟠 High |
| **Description** | `simulateRushSpurt()` uses `rand($minSpurt, $maxSpurt)` to add a random number of simulated commuters above the threshold, and uses `now()->subMinutes(rand(1, 5))` for fake `created_at` timestamps. **This fabricated data is written to the `commuter_trips` table** as real records. If `clearSimulatorData()` is forgotten, these fake trips pollute historical demand analysis and the `demand_history` table. |
| **Suggested Fix** | Mark all simulated records with a `is_simulated` boolean flag on the `commuter_trips` table. Exclude `is_simulated = true` records from all analytics, historical pattern queries, and demand history calculations. |

---

#### ISSUE-013
| Field | Detail |
|---|---|
| **File & Line** | [`DispatchIntelligenceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/DispatchIntelligenceController.php#L497-L498) — Lines 497–498 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | When no first stop is found for a route and status is `'red'`, the fallback GPS coordinates are hardcoded inline: `$firstLat = 14.5593; $firstLng = 121.0805`. These specific coordinates (near Pasig City center) are not read from `SystemSetting`. If the deployment area changes, these values become wrong. |
| **Suggested Fix** | Use `SystemSetting::get('map_default_latitude', 14.5593)` and `SystemSetting::get('map_default_longitude', 121.0805)` — consistent with how `BusController` and `RouteController` handle the same fallback. |

---

## MODULE 6 — Driver Performance Controller (Fleet)

### 📁 `app/Http/Controllers/Fleet/DriverPerformanceController.php` - DONE ✅

---

#### ISSUE-014
| Field | Detail |
|---|---|
| **File & Line** | [`DriverPerformanceController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/DriverPerformanceController.php#L199-L205) — Lines 199–205 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🔴 Critical |
| **Description** | The `messageDriver($id)` endpoint always returns `success: true` with the message `"Message thread initialized with Driver ID: {$id}"`, but **no message thread is actually created**. No database record is written. No notification is sent. The route exists and the UI presumably triggers it, but it is a complete stub. |
| **Suggested Fix** | Implement a `driver_messages` table (or integrate with a messaging service). Create a real message record on invocation. If the feature is not ready, disable the button in the UI and remove the route, or return a `501 Not Implemented` response. |

---

## MODULE 7 — Admin Analytics Controller 

### 📁 `app/Http/Controllers/Admin/AnalyticsController.php` - DONE ✅

---

#### ISSUE-015
| Field | Detail |
|---|---|
| **File & Line** | [`AnalyticsController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/AnalyticsController.php#L279-L281) — Lines 279–281 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟠 High |
| **Description** | In the trip passenger detail table (`$tripPaxTable`), three distinct fields — `boarded`, `alighted`, and `peakLoad` — are all set to the **same value**: `$s->passengers`. This means alighting count equals boarding count, and peak load equals current passengers. The `schedules` table does not track alighting separately. This makes the analytics table show false symmetry and useless peak load data. |
| **Suggested Fix** | Add `alighted_passengers` and `peak_passengers` columns to the `trip_logs` table (not `schedules`). Track these separately through the trip completion workflow. Until then, either omit these columns or label them clearly as "estimated." |

---

## MODULE 8 — Notification Service

### 📁 `app/Services/NotificationService.php` - DONE ✅

---

#### ISSUE-016
| Field | Detail |
|---|---|
| **File & Line** | [`NotificationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/NotificationService.php#L306-L313) — Lines 306–313 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🔴 Critical |
| **Description** | `sendEmailNotification()` does **not actually send any email**. The entire method body is a `Log::info()` call. The real Laravel `Mail::to($email)->queue(new NotificationMail($data))` is commented out. All notification functions — license expiry reminders, incident alerts, maintenance reminders — **silently fail** to deliver any notification. |
| **Suggested Fix** | Create a `NotificationMail` Mailable class. Configure SMTP/Mailgun in `.env`. Uncomment the `Mail::to()->queue()` call. Set up the Laravel email queue worker to process notifications asynchronously. |

---

## MODULE 9 — Validation Service

### 📁 `app/Services/ValidationService.php`  - DONE ✅

---

#### ISSUE-017
| Field | Detail |
|---|---|
| **File & Line** | [`ValidationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ValidationService.php#L14-L17) — Lines 14–17 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Philippines geographic bounds are defined as **PHP class constants** (`PHILIPPINES_LAT_MIN = 4.6`, etc.). These are correct but immutable. If the system were ever deployed in a different region or the service area boundaries were adjusted (e.g., a tighter Pasig City-only bounding box), the code would need recompilation. Note: `BusinessLogicService` uses the correct pattern of reading bounds from `SystemSetting`. `ValidationService` does not. |
| **Suggested Fix** | Replace the private constants with configurable values from `SystemSetting`: `SystemSetting::get('coordinates_bounds_north_latitude', 20.9)`. |

---

#### ISSUE-018
| Field | Detail |
|---|---|
| **File & Line** | [`ValidationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ValidationService.php#L136-L155) — Lines 136–155 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The `validatePolylineGeometry()` method uses `$maxJumpKm = 50` — a hardcoded threshold for flagging unrealistically large jumps between consecutive polyline points. The value `50` km may be appropriate for a national system but is too large for a city-level transit system like GoPasig. |
| **Suggested Fix** | Use `(float) SystemSetting::get('polyline_max_jump_km', 10)` — a tighter default suitable for Pasig City's geographic scale. |

---

#### ISSUE-019
| Field | Detail |
|---|---|
| **File & Line** | [`ValidationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ValidationService.php#L254-L266) — Lines 254–266 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Trip duration bounds are hardcoded: minimum `5` minutes and maximum `12 * 60` minutes. These limits are embedded directly in the validation method. |
| **Suggested Fix** | Use `SystemSetting::get('schedule_min_duration_minutes', 5)` and `SystemSetting::get('schedule_max_duration_minutes', 720)`. |

---

## MODULE 10 — Business Logic Service

### 📁 `app/Services/BusinessLogicService.php` - DONE ✅

---

#### ISSUE-020
| Field | Detail |
|---|---|
| **File & Line** | [`BusinessLogicService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/BusinessLogicService.php#L373-L376) — Lines 373–376 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | `checkRouteCapability()` checks `$bus->capacity < ($route->min_capacity ?? 30)`. The fallback value `30` is hardcoded in code. While `??` is used, the raw `30` literal should come from settings. |
| **Suggested Fix** | Use `$route->min_capacity ?? (int) SystemSetting::get('route_min_bus_capacity', 30)`. |

---

#### ISSUE-021
| Field | Detail |
|---|---|
| **File & Line** | [`BusinessLogicService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/BusinessLogicService.php#L25-L33) — Lines 25–33 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟠 High |
| **Description** | `checkDriverDailyHours()` queries schedules with `Schedule::where('driver_id', $driverId)->get()` — **no date filter is applied**. It counts total scheduled minutes across all historical records, not just today's schedules. This means a driver who worked last week will have those trips counted against today's limit. |
| **Suggested Fix** | Add a date filter: `Schedule::where('driver_id', $driverId)->whereDate('departure_time', Carbon::today())->get()`. |

---

## MODULE 11 — Schedule Conflict Service

### 📁 `app/Services/ScheduleConflictService.php` - DONE ✅

---

#### ISSUE-022
| Field | Detail |
|---|---|
| **File & Line** | [`ScheduleConflictService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/ScheduleConflictService.php#L229-L238) — Lines 229–238 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟠 High |
| **Description** | `checkDriverRestPeriod()` uses `$lastSchedule->arrival_time` — a `TIME` field — and parses it as a datetime via `Carbon::parse()`. When `arrival_time` contains only a time string like `"16:30:00"`, `Carbon::parse()` anchors it to today's date. The method then checks `isToday()` to detect cross-day scenarios, but this will be unreliable when `departure_time` also contains only time values. **The rest period check can incorrectly pass or fail depending on the time-only vs datetime format of the field.** |
| **Suggested Fix** | Store and compare all schedule times as full datetimes (`DATETIME` column type), not just `TIME`. Or compute the rest gap purely in minutes using string parsing, avoiding Carbon's datetime assumptions. |

---

## MODULE 12 — Admin Schedule Controller

### 📁 `app/Http/Controllers/Admin/ScheduleController.php` - DONE ✅

---

#### ISSUE-023
| Field | Detail |
|---|---|
| **File & Line** | [`ScheduleController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/ScheduleController.php#L94-L107) — Lines 94–107 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟠 High |
| **Description** | Driver lookup by initials is fragile: it uses `LIKE 'A%'` for first name and `LIKE 'B%'` for last name. This means if two drivers share the same initials (e.g., both "AB"), **only the first database match is used**, with no conflict error. The fallback then loads all drivers into memory (`Driver::all()`) to find by computed initials — an N+1 risk at scale. |
| **Suggested Fix** | Replace initials-based lookup with a proper `driver_id` field in the schedule creation form. Lookup should be by `id`, not by string pattern matching. |

---

## MODULE 13 — Fleet Controller

### 📁 `app/Http/Controllers/Fleet/FleetController.php` - DONE ✅

---

#### ISSUE-024
| Field | Detail |
|---|---|
| **File & Line** | [`FleetController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/FleetController.php#L189-L194) — Lines 189–194 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | `logActivity()` is an **empty method stub**. Its docblock says "for now, we will let AppServiceProvider or local logs capture it." It neither inserts a database record nor calls any logger. All calls to `logActivity()` for incidents, announcements, and resolutions **are silently dropped**. |
| **Suggested Fix** | Implement a real activity log. Create an `activity_logs` table or use the `spatie/laravel-activitylog` package. The method should write a record with type, description, actor (user_id), and timestamp. |

---

#### ISSUE-025
| Field | Detail |
|---|---|
| **File & Line** | [`FleetController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/FleetController.php#L272) — Line 272 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Bus GPS offline detection uses a hardcoded 2-minute threshold: `Bus::where('updated_at', '<', now()->subMinutes(2))`. This offline detection threshold should be configurable. |
| **Suggested Fix** | Use `(int) SystemSetting::get('bus_gps_offline_threshold_minutes', 2)`. |

---

## MODULE 14 — Admin Analytics Controller

### 📁 `app/Http/Controllers/Admin/AnalyticsController.php` - DONE ✅

---

#### ISSUE-026
| Field | Detail |
|---|---|
| **File & Line** | [`AnalyticsController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/AnalyticsController.php#L173) — Line 173 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The `peakHourStr` default is hardcoded as `'7–8 AM'` and is only overridden when a real peak hour record exists. If no schedule data exists for a route, the fallback is the hardcoded string instead of a dynamic or empty value. |
| **Suggested Fix** | Default to `'No data'` or `null` when peak hour cannot be computed from real data. |

---

## END OF MANDY PART ##


















## GAB'S PART ##




#### ISSUE-027
| Field | Detail |
|---|---|
| **File & Line** | [`AnalyticsController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/AnalyticsController.php#L249) — Line 249 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Stop boarding data (`$allStops`) is limited to `Stop::take(10)->get()` — a hardcoded `10` limit with no ordering or route filtering. The "top" stops shown in the chart may not actually be the busiest ones since no `ORDER BY` is applied before the `LIMIT`. |
| **Suggested Fix** | Order by boarding count: get all stops, compute boardings, sort descending, then take the configurable top N: `(int) SystemSetting::get('analytics_top_stops_count', 10)`. |

---

## MODULE 15 — Maintenance Management Controller (Fleet)

### 📁 `app/Http/Controllers/Fleet/MaintenanceManagementController.php`

---

#### ISSUE-028
| Field | Detail |
|---|---|
| **File & Line** | [`MaintenanceManagementController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/MaintenanceManagementController.php#L199) — Line 199 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | When `expected_duration_minutes` is null in the maintenance form, the default is `120` minutes hardcoded in PHP: `intval($validated['expected_duration_minutes']) : 120`. This fallback should be configurable. |
| **Suggested Fix** | Use `(int) SystemSetting::get('maintenance_default_duration_minutes', 120)`. |

---

#### ISSUE-029
| Field | Detail |
|---|---|
| **File & Line** | [`MaintenanceManagementController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/MaintenanceManagementController.php#L180) — Line 180 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | The `storeOrUpdate()` method validates `type` as `'in:Preventive,Corrective,Inspection'` — a hardcoded enum list in validation rules. If the admin wants to add a new maintenance type (e.g., `'Safety Check'`), they must edit the source code. |
| **Suggested Fix** | Store allowed maintenance types in `SystemSetting` (as a JSON array) or create a `maintenance_types` lookup table. Validate against `Rule::in(SystemSetting::getArray('maintenance_types', ['Preventive', 'Corrective', 'Inspection']))`. |

---

## MODULE 17 — Dashboard Data JS + Overview Map

### 📁 `public/js/admin-dashboard/dashboard-data.js` + `overview-map-simulation.js`

---

#### ISSUE-031
| Field | Detail |
|---|---|
| **File & Line** | [`dashboard-data.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/dashboard-data.js#L74-L78) — Lines 74–78 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Route colors are assigned by hardcoded `if/else if` conditionals based on route ID: Route 1 → `#378ADD`, Route 2 → `#639922`, Route 3 → `#BA7517`, else → `#E24B4A`. This ignores the `route.color` column stored in the database. If a new route is added or route colors are changed in the admin panel, the JS map will still show the old hardcoded color. **The database color is fetched but ignored.** |
| **Suggested Fix** | Replace the hardcoded color assignment block with: `routeColors[route.id.toString()] = route.color || '#003F87';`. The `route.color` value is already included in the API response. |

---

#### ISSUE-032
| Field | Detail |
|---|---|
| **File & Line** | [`overview-map-simulation.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/overview-map-simulation.js#L51-L53) — Lines 51–53 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The map center coordinates `14.5690 / 121.0680` and zoom level `13.5` are hardcoded as JS fallbacks inside `initOverviewMap()`. The comment says they rely on `mapCenterLat`, `mapCenterLng`, and `mapZoom` PHP-to-JS variables — but if those variables are not passed from the Blade template, the hardcoded Pasig defaults silently take over. |
| **Suggested Fix** | These fallback values are acceptable **only** if the Blade template always passes `mapCenterLat`/`mapCenterLng` via `window.GoPasigConfig`. Verify the admin dashboard Blade injects these from `SystemSetting`, and document it. |

---

## MODULE 18 — Analytics Data JS

### 📁 `public/js/admin-dashboard/analytics-data.js`

---

#### ISSUE-033
| Field | Detail |
|---|---|
| **File & Line** | [`analytics-data.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/analytics-data.js#L62) — Line 62 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟡 Medium |
| **Description** | The prediction busiest stop blurb hardcodes `~67 passengers` as an expected boarding count: `` `Expected highest boarding: ${topStopAll} · 7–8 AM · ~67 passengers` ``. This is a fabricated number injected directly into the prediction recommendation box, regardless of real historical data. |
| **Suggested Fix** | Replace the hardcoded `67` with an actual calculated value from the `stopBoardingData` array: e.g., `stopBoardingData[0]?.boarding_count || 'N/A'`. |

---

## MODULE 19 — Dispatch Intelligence JS

### 📁 `public/js/fleet-dashboard/dispatch-intelligence.js`

---

#### ISSUE-034
| Field | Detail |
|---|---|
| **File & Line** | [`dispatch-intelligence.js`](file:///c:/xampp/htdocs/GoPasig/public/js/fleet-dashboard/dispatch-intelligence.js#L278) — Line 278 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟠 High |
| **Description** | In `updateMlAccuracyTrackerDOM()` for Phase 3 (ML Accuracy), each historical pattern row is given a **random variance** via `Math.random()` and the "actual recorded" count is calculated as `p.total_commuters + variance`. The `errorPct` is also derived from this random offset. The UI then displays `96.4% Acc` as a **hardcoded accuracy badge** — this entire section is fabricated mock data posing as ML model accuracy. |
| **Suggested Fix** | Until a real ML model is integrated, clearly label this section as "Simulated / Demo Mode." Remove `Math.random()` for variance. If no real ML accuracy data exists, show an empty state or disable Phase 3 entirely. |

---

#### ISSUE-035
| Field | Detail |
|---|---|
| **File & Line** | [`dispatch-intelligence.js`](file:///c:/xampp/htdocs/GoPasig/public/js/fleet-dashboard/dispatch-intelligence.js#L240-L245) — Lines 240–245 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Inside `updateRecentDispatchesDOM()`, route colors are hardcoded as a local JS object: `{1: '#003F87', 2: '#BA7517', 3: '#639922', 4: '#E24B4A'}`. This is a second duplicate of the same hardcoded palette also found in ISSUE-031 (`dashboard-data.js`). Both need to be replaced with the database-sourced `routeColors` global. |
| **Suggested Fix** | Replace the local `const routeColors = {...}` block with a reference to the global `routeColors` object populated from the DB API: `const color = routeColors[log.route_id?.toString()] || '#888780';`. |

---

## MODULE 20 — Livewire: GeofenceDetector

### 📁 `app/Livewire/Commuter/GeofenceDetector.php`

---

#### ISSUE-036
| Field | Detail |
|---|---|
| **File & Line** | [`GeofenceDetector.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/GeofenceDetector.php#L126) — Line 126 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The boarding geofence radius of `15 meters` is hardcoded: `if ($dist <= 15)`. This threshold determines when a commuter is considered "on the bus." This should be configurable — in dense urban environments or for GPS accuracy tuning, this value may need to be adjusted. |
| **Suggested Fix** | Use `(int) SystemSetting::get('boarding_geofence_radius_meters', 15)`. |

---

#### ISSUE-037
| Field | Detail |
|---|---|
| **File & Line** | [`GeofenceDetector.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/GeofenceDetector.php#L161) — Line 161 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The fallback route color `'#003F87'` is hardcoded inline in two places inside `checkActiveTripTransitions()` (lines 161, 185). This is a tertiary instance of the same brand color fallback that should always be sourced from `config('brand.route_color_default')`. |
| **Suggested Fix** | Replace both inline `'#003F87'` literals with `config('brand.route_color_default', '#003F87')`. |

---

## MODULE 21 — Livewire: CommuterSchedule

### 📁 `app/Livewire/Commuter/CommuterSchedule.php`

---

#### ISSUE-038
| Field | Detail |
|---|---|
| **File & Line** | [`CommuterSchedule.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/CommuterSchedule.php#L82-L88) — Lines 82–88 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | The stop status logic for the trip timeline is **position-only**: stop `index=0` is always `'departed'`, stop `index=1` is always `'current'`, and all others are `'upcoming'`. This is not derived from real-time GPS data or actual trip progress. A bus that is physically past stop 4 will still show stop 1 as `'current'` on the commuter UI. |
| **Suggested Fix** | Derive the "current stop" from the active bus's `next_stop` field (matched to the route stop name) or from the trip's real-time position via the GPS tracker. The sequence-based approximation should only be a fallback. |

---

#### ISSUE-039
| Field | Detail |
|---|---|
| **File & Line** | [`CommuterSchedule.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/CommuterSchedule.php#L119-L143) — Lines 119–143 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | `setAlert()` creates an alert record associated with the **first stop of a route**, regardless of which trip the commuter actually selected. The commuter's actual origin stop is not used. Additionally, no push notification, email, or in-app notification is actually fired. The `alert-created` browser event only shows a UI toast. The `Alert` model record is created but never acted upon by any notification job or listener. |
| **Suggested Fix** | 1) Use the commuter's actual origin stop (from `activeStop` or `selectedTripId` context). 2) Create a background job that fires when the bus is X minutes from the commuter's stop and sends a browser push or in-app notification. |

---

## MODULE 22 — Livewire: Tracker

### 📁 `app/Livewire/Commuter/Tracker.php`

---

#### ISSUE-040
| Field | Detail |
|---|---|
| **File & Line** | [`Tracker.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/Tracker.php#L64-L66) — Lines 64–66 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | The `'idle'` bus status is applied when `$bus->speed == 0 && $bus->passengers == 0`, but this condition will **also match a bus at a terminal that hasn't departed yet** (speed=0, no passengers yet). A bus in `'inactive'` status should not appear as `'idle'` on the commuter tracker — but the query at line 48 explicitly includes `maintenance` and `breakdown` status buses in the map view, so commuters see buses that are not in service. |
| **Suggested Fix** | Filter the bus status query to only include `'active'` buses for the commuter-facing tracker (line 49). Show breakdown buses only to fleet operators. |

---

## MODULE 23 — Dashboard Service

### 📁 `app/Services/DashboardService.php`

---

#### ISSUE-041
| Field | Detail |
|---|---|
| **File & Line** | [`DashboardService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/DashboardService.php#L27) — Line 27 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | `getCommuterStats()['passengers_today']` returns `Schedule::sum('passengers')` — the **sum of passengers across all schedules of all time**, not just today. There is no `whereDate('created_at', today())` filter. This means the commuter dashboard "Passengers Today" KPI is a **cumulative all-time figure**, not a daily one. The same issue exists in `CommuterDashboardCacheService::dashboardData()` (line 129). |
| **Suggested Fix** | Add a date scope: `Schedule::whereDate('created_at', Carbon::today('Asia/Manila'))->sum('passengers')`. Apply identically in `CommuterDashboardCacheService`. |

---

## MODULE 24 — Fleet Analytics Controller

### 📁 `app/Http/Controllers/Fleet/AnalyticsController.php`

---

#### ISSUE-042
| Field | Detail |
|---|---|
| **File & Line** | [`Fleet/AnalyticsController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Fleet/AnalyticsController.php#L271-L276) — Lines 271–276 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | In `fetchSummaryData()`, bus status `'maintenance'` is relabeled as `'Breakdown'` in the bus utilization log: `'maintenance' => 'Breakdown'`. This incorrectly conflates scheduled preventive maintenance with a vehicle breakdown. Fleet managers reviewing the CSV export will see buses in routine maintenance labeled as "Breakdown," inflating the perceived breakdown rate. |
| **Suggested Fix** | Map statuses correctly: `'maintenance' => 'Maintenance'`, `'breakdown' => 'Breakdown'`, `'inactive' => 'Inactive'`. Add `'breakdown'` as a separate bus status if not already present. |

---

## MODULE 25 — GPSKalmanFilter Service

### 📁 `app/Services/GPSKalmanFilter.php`

---

#### ISSUE-043
| Field | Detail |
|---|---|
| **File & Line** | [`GPSKalmanFilter.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/GPSKalmanFilter.php#L11-L15) — Lines 11–15 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The Kalman filter process variance `$Q = 0.00002` and measurement variance `$R = 0.00015` are **hardcoded PHP static properties**. These are physics-based tuning constants for GPS smoothing. In production with different GPS hardware quality or bus speeds, these values may need recalibration. There is no mechanism to tune them without code changes. |
| **Suggested Fix** | Expose these as configurable settings: `SystemSetting::get('kalman_process_variance', 0.00002)` and `SystemSetting::get('kalman_measurement_variance', 0.00015)`. Cache the reads to avoid per-request DB hits. |

---

## MODULE 26 — AuthorizationService

### 📁 `app/Services/AuthorizationService.php`

---


#### ISSUE-044
| Field | Detail |
|---|---|
| **File & Line** | [`AuthorizationService.php`](file:///c:/xampp/htdocs/GoPasig/app/Services/AuthorizationService.php#L12-L63) — Lines 12–63 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Role strings `'admin'` and `'dispatcher'` are **hardcoded string literals** throughout all authorization methods. If a new role (e.g., `'supervisor'`) is added to the system, every method in this class must be manually updated. There is no `Role` enum, constant, or lookup table. |
| **Suggested Fix** | Define role constants: `const ROLE_ADMIN = 'admin'; const ROLE_DISPATCHER = 'dispatcher';`. Or use a `Role` enum (PHP 8.1+). Replace inline strings with constants to ensure refactoring safety. |

---

## MODULE 27 — Alerts Composer JS

### 📁 `public/js/admin-dashboard/alerts.js`

---

#### ISSUE-045
| Field | Detail |
|---|---|
| **File & Line** | [`alerts.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/alerts.js#L89-L91) — Lines 89–91 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟠 High |
| **Description** | In the Service Alert Composer, the front-end hardcodes `'Route A'`, `'Route B'`, and `'Route C'` as selectable routes, and maps database routes `'Route 1'`, `'Route 2'`, and `'Route 3'` to these. When creating/submitting an alert, it submits `'Route A'`, which is queried in `ServiceAlertController::store()` as `Route::where('name', 'Route A')->first()`. Because the database contains actual names like `'Route 1'`, the query returns `null`, saving `route_id = null` and breaking the link. |
| **Suggested Fix** | Fetch the actual route names dynamically from the Route API and render them as options in the affected routes selector instead of hardcoding Route A, B, C. |

---

## MODULE 28 — Analytics Charts & Interactions JS

### 📁 `public/js/admin-dashboard/analytics-charts.js` + `analytics-interactions.js`

---

#### ISSUE-046
| Field | Detail |
|---|---|
| **File & Line** | [`analytics-charts.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/analytics-charts.js#L46-L51) — Lines 46–51 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟡 Medium |
| **Description** | Five separate analytics charts (Hourly Ridership, Route Doughnut, Stop Boarding, Pax Load over time, 30-Day Historical Trend) use hardcoded fallback arrays with mock values when database queries return empty datasets or fail. This masks database/query errors and displays fake data to admins. |
| **Suggested Fix** | Show an "Empty State / No Data Available" overlay on the charts instead of rendering hardcoded fallback datasets. |

---

#### ISSUE-047
| Field | Detail |
|---|---|
| **File & Line** | [`analytics-interactions.js`](file:///c:/xampp/htdocs/GoPasig/public/js/admin-dashboard/analytics-interactions.js#L108-L122) — Lines 108–122 |
| **Category** | 🟡 MOCK / STATIC DATA |
| **Severity** | 🟡 Medium |
| **Description** | The route prediction tabs fallback to hardcoded text and numbers (e.g. `'532 pax / day'`, `'Expected highest boarding: Pasig City Hall'`) for `'Route A'`, `'Route B'`, and `'Route C'` when predictions fail or are empty. |
| **Suggested Fix** | Query the database or hide prediction metrics if the data is not available. Do not hardcode static text fallbacks for specific routes. |

---

## MODULE 29 — Admin Controllers (Dashboard, Driver, ServiceAlert)

### 📁 `app/Http/Controllers/Admin/DashboardController.php` + `DriverController.php` + `ServiceAlertController.php`

---

#### ISSUE-048 
| Field | Detail |
|---|---|
| **File & Line** | [`DashboardController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/DashboardController.php#L21) — Line 21 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | Fallback route name `'Pasig Line 1'` is hardcoded inline. Additionally, there is a mismatch in SystemSetting keys: `DashboardController` reads `bus_capacity_default`, while other components (like `Bus` model) read `default_bus_capacity`. |
| **Suggested Fix** | Use `SystemSetting::get('default_route_name', 'Route 1')` for fallbacks and unify all bus capacity setting queries to use the same key: `default_bus_capacity`. |

---

#### ISSUE-049
| Field | Detail |
|---|---|
| **File & Line** | [`DriverController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/DriverController.php#L106) — Line 106 |
| **Category** | 🟠 HARDCODED VALUES |
| **Severity** | 🟡 Medium |
| **Description** | The driver email domain `'@gopasig.com'` is hardcoded directly in the controller for auto-generating driver user accounts. If the agency domain changes, driver creation will generate incorrect email addresses. |
| **Suggested Fix** | Fetch domain from `SystemSetting::get('driver_email_domain', 'gopasig.com')` or Laravel configuration file. |

---

#### ISSUE-050
| Field | Detail |
|---|---|
| **File & Line** | [`ServiceAlertController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/Admin/ServiceAlertController.php#L219-L260) — Lines 219–260 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | In the alert resolution actions `resolve()` and `resolveAll()`, the controller changes the alert status in the database to `'resolved'`, but does not notify affected drivers or commuters, despite comments stating `// Issue 3.2.3: Notify commuters/drivers on suspension/resolution`. |
| **Suggested Fix** | Trigger an event or call `NotificationService` to broadcast a resolution notification to the affected route groups. |

---

## MODULE 30 — Login Controller (Auto-Login Backdoor)

### 📁 `app/Http/Controllers/LoginController.php`

---

#### ISSUE-051
| Field | Detail |
|---|---|
| **File & Line** | [`LoginController.php`](file:///c:/xampp/htdocs/GoPasig/app/Http/Controllers/LoginController.php#L50-L59) — Lines 50–59 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🔴 Critical |
| **Description** | The route `/autologin-dispatcher` calls `autoLoginDispatcher()`, which automatically finds the first user with the `'dispatcher'` role in the database and logs them in. This route is public and has no IP restriction, signature check, or environment guard (`app.env !== 'production'`), allowing unauthorized users to gain full dispatcher dashboard access. |
| **Suggested Fix** | Remove this route entirely from production routes, or guard it behind a local/testing environment check: `if (app()->environment('production')) abort(404);`. |

---

## MODULE 31 — Livewire: CommuterStops

### 📁 `app/Livewire/Commuter/CommuterStops.php`

---

#### ISSUE-052
| Field | Detail |
|---|---|
| **File & Line** | [`CommuterStops.php`](file:///c:/xampp/htdocs/GoPasig/app/Livewire/Commuter/CommuterStops.php#L58-L69) — Lines 58–69 |
| **Category** | 🔴 INCOMPLETE BUSINESS LOGIC |
| **Severity** | 🟡 Medium |
| **Description** | 1) The Haversine proximity distance is duplicated inline in this component rather than calling the centralized service helper, using hardcoded earth radius `6371` (km) instead of matching geofencing coordinates. 2) The "next bus" locator simply grabs the first active bus returned by the query without ordering by ETA or proximity. |
| **Suggested Fix** | 1) Call `GPSKalmanFilter::calculateDistance()` helper. 2) Sort query results by distance or proximity to give commuters the actual next arriving bus. |

---

## 📊 FINAL SUMMARY TABLE — All Issues by Module and Severity

| Module | File | Critical 🔴 | High 🟠 | Medium 🟡 | Total |
|---|---|---|---|---|---|
| **Driver Performance Service** | `DriverPerformanceService.php` | 2 | 0 | 2 | **4** |
| **Report Generation Service** | `ReportGenerationService.php` | 0 | 1 | 1 | **2** |
| **Route Performance Controller** | `RoutePerformanceController.php` | 1 | 0 | 2 | **3** |
| **Schedule Compliance Controller** | `ScheduleComplianceController.php` | 1 | 0 | 1 | **2** |
| **Dispatch Intelligence Controller** | `DispatchIntelligenceController.php` | 0 | 1 | 1 | **2** |
| **Driver Performance Controller** | `DriverPerformanceController.php` | 1 | 0 | 0 | **1** |
| **Admin Analytics Controller** | `Admin/AnalyticsController.php` | 0 | 1 | 2 | **3** |
| **Notification Service** | `NotificationService.php` | 1 | 0 | 0 | **1** |
| **Validation Service** | `ValidationService.php` | 0 | 0 | 3 | **3** |
| **Business Logic Service** | `BusinessLogicService.php` | 0 | 1 | 1 | **2** |
| **Schedule Conflict Service** | `ScheduleConflictService.php` | 0 | 1 | 0 | **1** |
| **Admin Schedule Controller** | `Admin/ScheduleController.php` | 0 | 1 | 0 | **1** |
| **Fleet Controller** | `FleetController.php` | 0 | 0 | 2 | **2** |
| **Maintenance Management Controller** | `MaintenanceManagementController.php` | 0 | 0 | 2 | **2** |
| **Admin Dashboard JS** | `dashboard-data.js` | 0 | 0 | 2 | **2** |
| **Analytics Data JS** | `analytics-data.js` | 0 | 0 | 1 | **1** |
| **Dispatch Intelligence JS** | `dispatch-intelligence.js` | 0 | 1 | 1 | **2** |
| **Livewire GeofenceDetector** | `GeofenceDetector.php` | 0 | 0 | 2 | **2** |
| **Livewire CommuterSchedule** | `CommuterSchedule.php` | 0 | 0 | 2 | **2** |
| **Livewire Tracker** | `Tracker.php` | 0 | 0 | 1 | **1** |
| **Dashboard Service** | `DashboardService.php` | 0 | 0 | 1 | **1** |
| **Fleet Analytics Controller** | `Fleet/AnalyticsController.php` | 0 | 0 | 1 | **1** |
| **GPS Kalman Filter** | `GPSKalmanFilter.php` | 0 | 0 | 1 | **1** |
| **Authorization Service** | `AuthorizationService.php` | 0 | 0 | 1 | **1** |
| **Alerts Composer JS** | `alerts.js` | 0 | 1 | 0 | **1** |
| **Analytics Charts JS** | `analytics-charts.js` | 0 | 0 | 2 | **2** |
| **Admin Controllers** | `Admin/*Controller.php` | 0 | 0 | 3 | **3** |
| **Login Controller** | `LoginController.php` | 1 | 0 | 0 | **1** |
| **Livewire CommuterStops** | `CommuterStops.php` | 0 | 0 | 1 | **1** |
| **GRAND TOTAL** | | **7** | **8** | **37** | **52** |

---

## 📋 COMPLETE ISSUES BY CATEGORY

### 🟡 MOCK / STATIC DATA (Fabricated or Synthetic Data)
| ID | Issue | File | Severity |
|---|---|---|---|
| ISSUE-001 | `calculatePassengerRating()` returns hardcoded `80` | `DriverPerformanceService.php` | 🔴 Critical |
| ISSUE-005 | `scheduled_hours` estimated as `schedules * 1.5` | `ReportGenerationService.php` | 🟠 High |
| ISSUE-007 | `rand(3, 12)` used as delay variance in analytics | `RoutePerformanceController.php` | 🔴 Critical |
| ISSUE-008 | `variance_minutes = 0` and `avg_dwell_seconds = 0` for all stops | `RoutePerformanceController.php` | 🟡 Medium |
| ISSUE-012 | `simulateRushSpurt()` writes fake records with `rand()` timestamps | `DispatchIntelligenceController.php` | 🟠 High |
| ISSUE-015 | `alighted` and `peakLoad` equal to `boarded` in trip table | `Admin/AnalyticsController.php` | 🟠 High |
| ISSUE-033 | `~67 passengers` hardcoded in prediction blurb | `analytics-data.js` | 🟡 Medium |
| ISSUE-034 | ML Accuracy Tracker uses `Math.random()` for "actual" data + hardcoded `96.4% Acc` | `dispatch-intelligence.js` | 🟠 High |
| ISSUE-046 | Fallback mock datasets on empty/failed charts queries | `analytics-charts.js` | 🟡 Medium |
| ISSUE-047 | Fallback mock text descriptions for Route A, B, C | `analytics-interactions.js` | 🟡 Medium |

### 🟠 HARDCODED VALUES (Should Come from DB or SystemSetting)
| ID | Issue | File | Severity |
|---|---|---|---|
| ISSUE-003 | `return 100` default on-time score for no-history drivers | `DriverPerformanceService.php` | 🟡 Medium |
| ISSUE-004 | Incident penalty `* 10` in production code path | `DriverPerformanceService.php` | 🟡 Medium |
| ISSUE-006 | Ranking weights `0.4/0.4/0.2` duplicated across two services | `ReportGenerationService.php` | 🟡 Medium |
| ISSUE-009 | Fallback headway target `15` inline | `RoutePerformanceController.php` | 🟡 Medium |
| ISSUE-013 | GPS fallback `14.5593/121.0805` inline | `DispatchIntelligenceController.php` | 🟡 Medium |
| ISSUE-017 | Philippines coordinate bounds as PHP constants | `ValidationService.php` | 🟡 Medium |
| ISSUE-018 | Max polyline jump `50 km` hardcoded | `ValidationService.php` | 🟡 Medium |
| ISSUE-019 | Trip duration min `5` / max `720` hardcoded | `ValidationService.php` | 🟡 Medium |
| ISSUE-020 | Route min capacity fallback `30` inline | `BusinessLogicService.php` | 🟡 Medium |
| ISSUE-025 | GPS offline threshold `2 minutes` hardcoded | `FleetController.php` | 🟡 Medium |
| ISSUE-026 | Peak hour default `'7–8 AM'` hardcoded | `Admin/AnalyticsController.php` | 🟡 Medium |
| ISSUE-027 | Top stops `take(10)` with no ordering | `Admin/AnalyticsController.php` | 🟡 Medium |
| ISSUE-028 | Maintenance default duration `120` minutes hardcoded | `MaintenanceManagementController.php` | 🟡 Medium |
| ISSUE-029 | Maintenance type enum hardcoded in validation | `MaintenanceManagementController.php` | 🟡 Medium |
| ISSUE-031 | Route colors hardcoded by ID in JS (`#378ADD`, etc.) | `dashboard-data.js` | 🟡 Medium |
| ISSUE-032 | Map center coords `14.5690/121.0680` hardcoded JS fallback | `overview-map-simulation.js` | 🟡 Medium |
| ISSUE-035 | Duplicate hardcoded route color palette in dispatch JS | `dispatch-intelligence.js` | 🟡 Medium |
| ISSUE-036 | Boarding geofence radius `15 meters` hardcoded | `GeofenceDetector.php` | 🟡 Medium |
| ISSUE-037 | Brand color `'#003F87'` hardcoded inline (not from config) | `GeofenceDetector.php` | 🟡 Medium |
| ISSUE-043 | Kalman filter constants `Q=0.00002`, `R=0.00015` hardcoded | `GPSKalmanFilter.php` | 🟡 Medium |
| ISSUE-044 | Role strings `'admin'`/`'dispatcher'` hardcoded, no enum/constant | `AuthorizationService.php` | 🟡 Medium |
| ISSUE-045 | UI/JS composer route selection mappings break controller lookups | `alerts.js` | 🟠 High |
| ISSUE-048 | Fallback route name `'Pasig Line 1'` and settings key mismatch | `DashboardController.php` | 🟡 Medium |
| ISSUE-049 | Hardcoded driver email domain `'@gopasig.com'` | `DriverController.php` | 🟡 Medium |

### 🔴 INCOMPLETE BUSINESS LOGIC (Stubs, Missing Workflows, Logic Gaps)
| ID | Issue | File | Severity |
|---|---|---|---|
| ISSUE-002 | `debug_backtrace()` switches formula between prod and test | `DriverPerformanceService.php` | 🔴 Critical |
| ISSUE-010 | Delay of `duration * 0.1` fabricated for missing data | `ScheduleComplianceController.php` | 🔴 Critical |
| ISSUE-011 | No `'early'` departure status; early departures untracked | `ScheduleComplianceController.php` | 🟡 Medium |
| ISSUE-014 | `messageDriver()` is a stub — no actual message system | `DriverPerformanceController.php` | 🔴 Critical |
| ISSUE-016 | `sendEmailNotification()` only logs — never sends email | `NotificationService.php` | 🔴 Critical |
| ISSUE-021 | `checkDriverDailyHours()` has no date filter | `BusinessLogicService.php` | 🟠 High |
| ISSUE-022 | Rest period check unreliable (`TIME` vs `DATETIME` in Carbon) | `ScheduleConflictService.php` | 🟠 High |
| ISSUE-023 | Driver initials lookup is fragile (ambiguous, full table scan) | `Admin/ScheduleController.php` | 🟠 High |
| ISSUE-024 | `logActivity()` is an empty stub | `FleetController.php` | 🟡 Medium |
| ISSUE-038 | Stop status is position-only, not real-time GPS-derived | `CommuterSchedule.php` | 🟡 Medium |
| ISSUE-039 | `setAlert()` uses wrong stop (first route stop, not commuter's stop); alert never fires | `CommuterSchedule.php` | 🟡 Medium |
| ISSUE-040 | Maintenance/breakdown buses appear on commuter-facing tracker | `Tracker.php` | 🟡 Medium |
| ISSUE-041 | `passengers_today` sums all-time data, no date filter | `DashboardService.php` | 🟡 Medium |
| ISSUE-042 | `'maintenance'` bus status mislabeled as `'Breakdown'` in CSV | `Fleet/AnalyticsController.php` | 🟡 Medium |
| ISSUE-050 | `resolve()` alert actions do not broadcast resolution warnings | `ServiceAlertController.php` | 🟡 Medium |
| ISSUE-051 | Public autologin backdoor endpoint logs in as dispatcher | `LoginController.php` | 🔴 Critical |
| ISSUE-052 | Proximity calculation duplicated inline and next bus selection gap | `CommuterStops.php` | 🟡 Medium |

---

## 🚨 TOP PRIORITY FIXES (Recommended Order)

### 🔴 Critical — Fix First
1. **ISSUE-002** — Remove `debug_backtrace()` from `DriverPerformanceService::recalculate()`. Single formula, always.
2. **ISSUE-016** — Implement real email delivery in `NotificationService`. Current state: zero notifications ever sent.
3. **ISSUE-014** — Remove or properly implement `messageDriver()` stub.
4. **ISSUE-007** — Remove `rand(3, 12)` from `RoutePerformanceController`. Real data or `null`.
5. **ISSUE-010** — Remove `duration * 0.1` fake delay in `ScheduleComplianceController`.
6. **ISSUE-001** — Implement real passenger rating or make default configurable.
7. **ISSUE-051** — Remove or guard the `/autologin-dispatcher` route.

### 🟠 High — Fix Next Sprint
8. **ISSUE-034** — Remove `Math.random()` ML accuracy faker; label Phase 3 as demo/simulated.
9. **ISSUE-012** — Add `is_simulated` flag to `commuter_trips` to isolate simulator data.
10. **ISSUE-015** — Track `alighted_passengers` and `peak_passengers` separately.
11. **ISSUE-021** — Add date filter to `checkDriverDailyHours()` in `BusinessLogicService`.
12. **ISSUE-022** — Fix `TIME` vs `DATETIME` Carbon parsing in `ScheduleConflictService`.
13. **ISSUE-023** — Replace driver initials lookup with `driver_id` in form submission.
14. **ISSUE-045** — Fix Route A/B/C mapping discrepancy in alerts composer JS.

### 🟡 Medium — Fix in Ongoing Maintenance
15. **ISSUE-024** — Implement `logActivity()` with real database persistence.
16. **ISSUE-041** — Fix `passengers_today` to use today's date filter in `DashboardService`.
17. **ISSUE-031 / ISSUE-035** — Replace hardcoded JS route colors with DB-sourced `routeColors` global.
18. **ISSUE-038 / ISSUE-039** — Fix commuter schedule stop tracking to use real GPS position.
19. **ISSUE-040** — Filter `maintenance`/`breakdown` buses from the commuter-facing tracker.
20. **ISSUE-042** — Fix `maintenance` → `Breakdown` mislabeling in Fleet Analytics CSV export.
21. **ISSUE-029** — Move maintenance type enum to `SystemSetting` or database lookup table.
22. **ISSUE-044** — Define role constants or PHP 8.1 enum in `AuthorizationService`.
23. **ISSUE-046 / ISSUE-047** — Replace fallback charts mock data with empty state templates.
24. **ISSUE-048** — Unify SystemSetting key references for default bus capacity.
25. **ISSUE-049** — Externalize generated driver email domain parameters.
26. **ISSUE-050** — Implement resolution broadcasts when alerts are cleared.
27. **ISSUE-052** — Deduplicate geofence proximity math in Livewire stops component.
