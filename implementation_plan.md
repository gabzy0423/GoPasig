# Critical Telemetry Pipeline Restoration

Restore the live GPS telemetry pipeline by implementing the missing Laravel database queue infrastructure, running a queue worker, and validating end-to-end job execution from the Driver Dashboard to the Fleet Monitor.

## User Review Required

> [!IMPORTANT]
> The database currently lacks the standard `jobs` and `failed_jobs` tables, which causes `ProcessGPSJob::dispatch()` to fail with a `QueryException`. We will generate these standard Laravel migrations and run them.

> [!NOTE]
> We will run a persistent queue worker in the background using `php artisan queue:work --tries=3` during our verification phases.

## Open Questions

None. The objective and verified root causes are clear and have been documented in the telemetry investigation.

## Proposed Changes

### Laravel Queue Infrastructure

Since queue tables are missing, we will generate and run the standard Laravel migrations.

#### [NEW] [2026_07_15_220900_create_jobs_table.php](file:///c:/xampp/htdocs/GoPasig/database/migrations/2026_07_15_220900_create_jobs_table.php)
The migration file generated automatically by `php artisan make:queue-table` to create the `jobs` table.

#### [NEW] [2026_07_15_220901_create_failed_jobs_table.php](file:///c:/xampp/htdocs/GoPasig/database/migrations/2026_07_15_220901_create_failed_jobs_table.php)
The migration file generated automatically by `php artisan make:queue-failed-table` to create the `failed_jobs` table.

---

## Verification Plan

### Automated Tests
- Run existing automated tests to ensure no regressions:
  ```powershell
  php artisan test
  ```

### Manual Verification
1. **Queue Creation & Configuration**:
   - Run queue table generation: `php artisan make:queue-table` and `php artisan make:queue-failed-table`
   - Run migrations: `php artisan migrate`
   - Verify configuration: Confirm `QUEUE_CONNECTION=database` in `.env` and `config('queue.default')` returns `database`
2. **Start Queue Worker**:
   - Start the Laravel queue worker using `php artisan queue:work --tries=3` as a background process.
3. **End-to-End Telemetry Validation (Layer 1-10)**:
   - Generate one GPS update from the Driver Dashboard during an active trip.
   - **Layer 1 (HTTP Request)**: Confirm `POST /driver/trip/gps` returns HTTP 200 OK.
   - **Layer 2 (GPS_TRACE)**: Verify log sequence in `storage/logs/laravel.log` matches: `A`, `B`, `B2`, `C`, `C2`, `D`, `E`, `M`.
   - **Layer 3 (Jobs Table)**: Confirm job is queued, then consumed by the worker.
   - **Layer 4 (ProcessGPSJob)**: Verify trace sequence: `F`, `F2`, `G`, `G2`, `H`, `H2`, `I`, `I2`, `J`, `J2`, `K`, `K2`.
   - **Layer 5 (GPS Logs)**: Verify `gps_logs.processing_status` goes from `pending` -> `processed`, and `filtered_lat`/`filtered_lng` are populated.
   - **Layer 6 (Vehicle Position)**: Verify `vehicle_positions` contains live position records.
   - **Layer 7 (Bus Synchronization)**: Verify `buses` fields (lat, lng, speed, eta, next_stop) sync correctly.
   - **Layer 8 & 9 (Spatial Monitoring & ETA Engine)**: Verify `L`, `L2`, `L3` and `L-ETA`, `L-ETA2` logs are outputted without exception.
   - **Layer 10 (Fleet Monitor)**: Verify live driver movements and coordinates populate correctly in the Fleet Monitor interface.
