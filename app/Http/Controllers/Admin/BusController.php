<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\MaintenanceRecord;
use App\Models\Trip;
use App\Models\SystemSetting;
use App\Models\Route;
use App\Services\ValidationService;
use App\Services\BusStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusController extends Controller
{
    /**
     * Display a listing of buses with pagination and query filters.
     */
    public function index(Request $request)
    {
        $query = Bus::with('route');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $status = strtolower($request->input('status'));
            if ($status === 'standby' || $status === 'inactive') {
                $query->where('status', 'inactive');
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%{$search}%")
                  ->orWhere('fleet_number', 'like', "%{$search}%")
                  ->orWhere('driver_name', 'like', "%{$search}%")
                  ->orWhereHas('route', function ($rq) use ($search) {
                      $rq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Retrieve active ongoing trip IDs to check trip status
        $activeBusIds = DB::table('trips')
            ->where('status', 'ongoing')
            ->pluck('bus_id')
            ->toArray();

        $records = $query->orderBy('fleet_number', 'asc')->paginate(10);

        // Map has_active_trip to keep UI features synchronized
        $records->getCollection()->transform(function ($bus) use ($activeBusIds) {
            $bus->has_active_trip = in_array($bus->id, $activeBusIds);
            return $bus;
        });

        return response()->json($records);
    }
    /**
     * Show the form for registering a new bus.
     */
    public function create()
    {
        $routes = Route::getCanonicalProductionCached();
        // Get all capacity settings from SystemSetting instead of hardcoding
        $defaultCapacity = (int) SystemSetting::get('default_bus_capacity', 45);
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);
        $allowedStatuses = BusStateService::getValidInitialStatuses();

        return view('admin.bus.create', compact('routes', 'defaultCapacity', 'minCapacity', 'maxCapacity', 'allowedStatuses'));
    }

    /**
     * Display the specified bus asset details.
     */
    public function show(Bus $bus)
    {
        $bus->load(['route', 'maintenanceRecords' => function ($q) {
            $q->orderByDesc('completed_at');
        }]);
        return view('admin.bus.view', compact('bus'));
    }

    public function edit(Bus $bus)
    {
        $routes = Route::getCanonicalProductionCached();
        $drivers = \App\Models\Driver::all();
        $allowedStatuses = array_unique(array_merge([$bus->status], BusStateService::getValidManualTransitions($bus->status)));
        return view('admin.bus.edit', compact('bus', 'routes', 'drivers', 'allowedStatuses'));
    }

    /**
     * Store a newly created bus in the database.
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create buses'
            ], 403);
        }
        // Get capacity constraints from SystemSetting instead of hardcoding
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);

        $validated = $request->validate([
            'plate_number'          => 'required|string|max:20|unique:buses,plate_number',
            'fleet_number'          => 'required|string|unique:buses,fleet_number|regex:/^BUS-\d+$/',
            'vin'                   => 'required|string|size:17|unique:buses,vin|regex:/^[A-HJ-NPR-Z0-9]{17}$/',
            'manufacturer'          => 'required|string|max:100',
            'manufacturer_custom'   => 'required_if:manufacturer,Others|nullable|string|max:100',
            'model'                 => 'required|string|max:100',
            'year_model'            => 'required|integer|min:1980|max:' . (date('Y') + 2),
            'battery_capacity_kwh'  => 'required|numeric|min:10|max:1000',
            'charging_port_type'    => 'required|string|in:CCS2,GB/T,CHAdeMO',
            'max_charging_power_kw' => 'required|numeric|min:10|max:500',
            'capacity'              => "required|integer|min:{$minCapacity}|max:{$maxCapacity}",
        ]);

        // Get default coordinates from SystemSetting instead of hardcoding
        $defaultLat = (float) SystemSetting::get('map_default_latitude', 14.5593);
        $defaultLng = (float) SystemSetting::get('map_default_longitude', 121.0805);

        // Validate GPS coordinates (Issue 5.1.1)
        $gpsValidation = ValidationService::validateGPSCoordinates($defaultLat, $defaultLng);
        if (!$gpsValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid default GPS coordinates: ' . $gpsValidation['message']
            ], 422);
        }

        $manufacturer = $validated['manufacturer'] === 'Others' ? $validated['manufacturer_custom'] : $validated['manufacturer'];

        $bus = Bus::create([
            'plate_number'          => $validated['plate_number'],
            'fleet_number'          => $validated['fleet_number'],
            'vin'                   => $validated['vin'],
            'manufacturer'          => $manufacturer,
            'model'                 => $validated['model'],
            'year_model'            => $validated['year_model'],
            'battery_capacity_kwh'  => $validated['battery_capacity_kwh'],
            'charging_port_type'    => $validated['charging_port_type'],
            'max_charging_power_kw' => $validated['max_charging_power_kw'],
            'capacity'              => $validated['capacity'],
            'status'                => Bus::STATUS_INACTIVE, // Force Inactive status (only valid initial lifecycle state)
            'route_id'              => null,
            'driver_name'           => Bus::getDefaultDriverName(),
            'speed'                 => Bus::getInitialSpeed(),
            'passengers'            => Bus::getInitialPassengers(),
            'next_stop'             => Bus::getDefaultNextStop(),
            'eta'                   => Bus::getInitialEta(),
            'lat'                   => $defaultLat,
            'lng'                   => $defaultLng,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully registered!',
            'bus'     => $bus
        ], 201);
    }

    /**
     * Update the specified bus in the database.
     */
    public function update(Request $request, Bus $bus)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can update buses'
            ], 403);
        }
        // Get capacity constraints from SystemSetting instead of hardcoding
        $minCapacity = (int) SystemSetting::get('bus_capacity_min', 10);
        $maxCapacity = (int) SystemSetting::get('bus_capacity_max', 150);

        $allowedStatuses = array_diff(
            BusStateService::getValidManualTransitions($bus->status),
            [Bus::STATUS_ACTIVE]
        );
        $allowedStatuses[] = $bus->status;
        $allowedStatuses = array_unique($allowedStatuses);

        // Immutable Vehicle Identity enforcement: Ignore client-supplied updates to plate_number and vin
        if ($request->has('plate_number')) {
            $request->offsetUnset('plate_number');
        }
        if ($request->has('vin')) {
            $request->offsetUnset('vin');
        }

        $validated = $request->validate([
            'fleet_number'          => [
                'required',
                'string',
                'regex:/^BUS-\d+$/',
                Rule::unique('buses')->ignore($bus->id),
            ],
            'manufacturer'          => 'required|string|max:100',
            'manufacturer_custom'   => 'required_if:manufacturer,Others|nullable|string|max:100',
            'model'                 => 'required|string|max:100',
            'year_model'            => 'required|integer|min:1980|max:' . (date('Y') + 2),
            'battery_capacity_kwh'  => 'required|numeric|min:10|max:1000',
            'charging_port_type'    => 'required|string|in:CCS2,GB/T,CHAdeMO',
            'max_charging_power_kw' => 'required|numeric|min:10|max:500',
            'capacity'              => "required|integer|min:{$minCapacity}|max:{$maxCapacity}",
            'route_id'              => ['nullable', 'exists:routes,id', \Illuminate\Validation\Rule::in(Route::getCanonicalProductionCached()->pluck('id')->all())],
            'driver_name'           => 'nullable|string|max:100',
            'status'                => [
                'required',
                Rule::in($allowedStatuses)
            ],
        ]);

        try {
            DB::transaction(function () use ($bus, $validated) {
                // Lock the bus record to protect against race conditions
                $bus = Bus::where('id', $bus->id)->lockForUpdate()->first();
                if (!$bus) {
                    throw new \Exception("Bus record not found for update locking.");
                }

                $newStatus = $validated['status'];
                $capacityChanged = (int) $validated['capacity'] !== (int) $bus->capacity;
                if ($capacityChanged) {
                    if ((int) $validated['capacity'] < (int) $bus->passengers) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'capacity' => "Capacity cannot be reduced below the current onboard passenger count ({$bus->passengers}).",
                        ]);
                    }

                    $hasActiveTrip = Trip::where('bus_id', $bus->id)
                        ->whereIn('status', ['ongoing', 'dispatched'])
                        ->exists();

                    $operationalStatuses = ['ready', 'operating'];
                    $isOperationalCapacityEdit = in_array(strtolower((string) $bus->status), $operationalStatuses, true)
                        || in_array(strtolower((string) $newStatus), $operationalStatuses, true);

                    if ($isOperationalCapacityEdit || $hasActiveTrip) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'capacity' => 'Capacity cannot be edited while the bus is ready, operating, or assigned to an active dispatch.',
                        ]);
                    }
                }
                $newDriverName = $validated['driver_name'] ?? Bus::getDefaultDriverName();
                $newRouteId = isset($validated['route_id']) ? $validated['route_id'] : null;

                $manufacturer = $validated['manufacturer'] === 'Others' ? $validated['manufacturer_custom'] : $validated['manufacturer'];

                // Update non-transition profile fields
                $bus->update([
                    'fleet_number'          => $validated['fleet_number'],
                    'manufacturer'          => $manufacturer,
                    'model'                 => $validated['model'],
                    'year_model'            => $validated['year_model'],
                    'battery_capacity_kwh'  => $validated['battery_capacity_kwh'],
                    'charging_port_type'    => $validated['charging_port_type'],
                    'max_charging_power_kw' => $validated['max_charging_power_kw'],
                    'capacity'              => $validated['capacity'],
                ]);

                if ($newStatus === Bus::STATUS_ACTIVE) {
                    if ($newDriverName === Bus::getDefaultDriverName() || is_null($newRouteId)) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator(
                                [],
                                [],
                                [],
                                ['status' => 'An active bus must have an assigned driver and route.']
                            )
                        );
                    }

                    $nameParts = explode(' ', $newDriverName);
                    $firstName = $nameParts[0];
                    $lastName  = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
                    $driverModel = \App\Models\Driver::where('first_name', $firstName)
                        ->where('last_name', $lastName)
                        ->first();

                    if (!$driverModel) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator(
                                [],
                                [],
                                [],
                                ['driver_name' => "Driver '{$newDriverName}' not found."]
                            )
                        );
                    }

                    $routeModel = Route::find($newRouteId);
                    if (!$routeModel) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator(
                                [],
                                [],
                                [],
                                ['route_id' => 'Selected route is invalid.']
                            )
                        );
                    }

                    if ($newStatus !== $bus->status) {
                        BusStateService::transition($bus, $newStatus, 'Status update via admin', $driverModel, $routeModel);
                    } else {
                        // Reassignment: status is already active, but driver/route changed
                        $fullName = $driverModel->first_name . ' ' . $driverModel->last_name;
                        if ($bus->driver_name !== $fullName || $bus->route_id !== $routeModel->id) {
                            BusStateService::reassignDriverAndRoute($bus, $driverModel, $routeModel, 'Driver/Route reassigned via admin');
                        }
                    }
                } else {
                    if ($newStatus !== $bus->status) {
                        BusStateService::transition($bus, $newStatus, 'Status update via admin');
                    }
                }
            });
        } catch (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'valid_transitions' => $e->validTransitions,
            ], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully updated!',
            'bus'     => $bus->fresh()
        ]);
    }

    /**
     * Remove the specified bus from the database.
     */
    public function destroy(Bus $bus)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can delete buses'
            ], 403);
        }

        // Existing guards (ongoing trip, active maintenance)
        $hasOngoingTrip = Trip::where('bus_id', $bus->id)
            ->where('status', 'ongoing')->exists();

        $hasActiveMaintenance = MaintenanceRecord::where('bus_id', $bus->id)
            ->whereNotIn('status', ['completed', 'cancelled'])->exists();

        // block deletion if historical records exist
        $hasHistoricalTrips = Trip::where('bus_id', $bus->id)->exists();

        if ($hasOngoingTrip || $hasActiveMaintenance) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bus with active trips or maintenance records.'
            ], 422);
        }

        if ($hasHistoricalTrips) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bus with historical trip records. Deactivate the bus instead to preserve operational data.'
            ], 422);
        }

        $bus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bus successfully deleted!'
        ]);
    }

    /**
     * Assign a route to the specified bus.
     */
    public function assignRoute(Bus $bus, Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can assign routes'
            ], 403);
        }
        $validated = $request->validate([
            'route_id' => ['nullable', 'exists:routes,id', \Illuminate\Validation\Rule::in(Route::getCanonicalProductionCached()->pluck('id')->all())],
        ]);

        $bus->update(['route_id' => $validated['route_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Bus route assignment updated successfully!'
        ]);
    }
}
