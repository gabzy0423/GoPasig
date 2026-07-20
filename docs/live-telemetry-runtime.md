# Live Telemetry Runtime Contract

Driver GPS telemetry is processed synchronously during an active live trip. The driver POST request creates the `gps_logs` row, immediately runs `TelemetryProcessingService::processGpsLog()`, and updates `vehicle_positions` plus the `buses` fallback coordinates before returning a successful response.

Do not route active live GPS packets through a delayed database queue for the 6-bus prototype. A delayed `ProcessGPSJob` can execute after the trip has already become `completed` or the GPS session has become `CLOSED`, causing the trip guard to reject valid telemetry and leaving the live maps on stale `buses.lat/lng` fallback coordinates.

`ProcessGPSJob` remains as a delegated fallback path for non-live or explicitly queued workloads. If any deployment uses that queued path, a persistent queue worker is mandatory:

```bash
php artisan queue:work --tries=3
```

For active live trips, both `/fleet/api/bus-gps-positions` and `/admin/api/fleet-data` should report `coordinate_source = vehicle_position` after the first valid GPS packet is received.
