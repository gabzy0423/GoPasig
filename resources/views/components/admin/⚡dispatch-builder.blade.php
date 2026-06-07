<?php
 
use Livewire\Component;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
 
new class extends Component
{
    public $availableBuses = [];
    public $availableDrivers = [];
    public $routes = [];
 
    public $selectedRoute = '';
    public $selectedBus = '';
    public $selectedDriver = '';
    public $departureTime = '';
 
    public function mount()
    {
        $this->loadData();
    }
 
    public function loadData()
    {
        // 1. Fetch routes
        $this->routes = Route::all()->map(function($r) {
            return [
                'id' => $r->id,
                'name' => $r->name . ' — ' . $r->description
            ];
        })->toArray();
 
        // 2. Fetch inactive buses (standby pool)
        $this->availableBuses = Bus::where('status', 'inactive')
            ->get()
            ->map(function($bus) {
                return [
                    'plate' => $bus->plate_number,
                    'status' => 'Ready'
                ];
            })->toArray();
 
        // 3. Fetch drivers who are active/inactive but not currently assigned to any active bus
        $activeBusDrivers = Bus::where('status', 'active')
            ->pluck('driver_name')
            ->toArray();
 
        $this->availableDrivers = Driver::whereIn('status', ['active', 'inactive'])
            ->get()
            ->filter(function($driver) use ($activeBusDrivers) {
                $fullName = $driver->first_name . ' ' . $driver->last_name;
                return !in_array($fullName, $activeBusDrivers);
            })
            ->map(function($driver) {
                return [
                    'name' => $driver->first_name . ' ' . $driver->last_name,
                    'status' => 'Standby'
                ];
            })->values()->toArray();
    }
 
    public function createDispatch()
    {
        $this->validate([
            'selectedRoute' => 'required|exists:routes,id',
            'selectedBus' => 'required',
            'selectedDriver' => 'required',
            'departureTime' => 'required',
        ]);
 
        DB::transaction(function() {
            // Find Bus
            $bus = Bus::where('plate_number', $this->selectedBus)->firstOrFail();
            
            // Find Driver
            $driverNameParts = explode(' ', $this->selectedDriver);
            $firstName = $driverNameParts[0];
            $lastName = isset($driverNameParts[1]) ? implode(' ', array_slice($driverNameParts, 1)) : '';
            $driver = Driver::where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->firstOrFail();
 
            // Find Route
            $route = Route::findOrFail($this->selectedRoute);
 
            // 1. Update Bus status to active, assign route and driver
            $bus->update([
                'status' => 'active',
                'route_id' => $route->id,
                'driver_name' => $this->selectedDriver,
                'next_stop' => 'SPED Terminal (Caruncho Ave.)', // initial default stop
                'eta' => 5
            ]);
 
            // 2. Update Driver status to active and assign bus and route
            $driver->update([
                'status' => 'active',
                'assigned_bus' => $bus->plate_number,
                'assigned_route' => $route->id,
            ]);
 
            // 3. Log a new Trip in trips table
            $trip = Trip::create([
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'status' => 'ongoing',
                'started_at' => now(),
            ]);
 
            // 4. Log in dispatch_logs table
            DB::table('dispatch_logs')->insert([
                'trip_id' => $trip->id,
                'dispatched_by' => Auth::id() ?: 1, // fallback to admin user ID 1
                'dispatched_at' => now(),
                'notes' => 'Dispatched dynamically via Central Dispatcher Dashboard.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
 
        // Fire browser dispatch event to let JS know to reload fleet visualizer
        $this->dispatch('dispatchSuccessful');
 
        // Reset fields
        $this->reset(['selectedRoute', 'selectedBus', 'selectedDriver', 'departureTime']);
        
        // Reload Pools
        $this->loadData();
    }
};
?>
 
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Left Side: Pool of Available Resources (Buses & Drivers) -->
    <div class="lg:col-span-1 space-y-6 flex flex-col">
        <!-- Available Buses Card Pool -->
        <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col max-h-[300px]">
            <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2.5 shrink-0 block">Available Buses (Pool)</span>
            <div class="flex-1 overflow-y-auto mt-3 space-y-2.5 scrollbar-thin scrollbar-thumb-slate-200">
                @forelse($availableBuses as $bus)
                    <div class="flex items-center justify-between border border-slate-100 bg-slate-50/50 px-3 py-2 rounded-lg hover:border-[#003F87]/20 transition">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-bus text-slate-400 text-base"></i>
                            <span class="text-xs font-extrabold text-slate-800">{{ $bus['plate'] }}</span>
                        </div>
                        <span class="text-[10px] font-bold text-[#639922] bg-[#E8F4E0] px-2 py-0.5 rounded uppercase">{{ $bus['status'] }}</span>
                    </div>
                @empty
                    <div class="text-center py-6 text-slate-400 text-xs font-semibold uppercase">No buses in standby pool</div>
                @endforelse
            </div>
        </div>
 
        <!-- Available Drivers Card Pool -->
        <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col max-h-[300px]">
            <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2.5 shrink-0 block">Available Drivers (Pool)</span>
            <div class="flex-1 overflow-y-auto mt-3 space-y-2.5 scrollbar-thin scrollbar-thumb-slate-200">
                @forelse($availableDrivers as $driver)
                    <div class="flex items-center justify-between border border-slate-100 bg-slate-50/50 px-3 py-2 rounded-lg hover:border-[#003F87]/20 transition">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-id text-slate-400 text-base"></i>
                            <span class="text-xs font-extrabold text-slate-800">{{ $driver['name'] }}</span>
                        </div>
                        <span class="text-[10px] font-bold text-[#639922] bg-[#E8F4E0] px-2 py-0.5 rounded uppercase">{{ $driver['status'] }}</span>
                    </div>
                @empty
                    <div class="text-center py-6 text-slate-400 text-xs font-semibold uppercase">No drivers on standby</div>
                @endforelse
            </div>
        </div>
    </div>
 
    <!-- Right Side: Active Assignment Form -->
    <div class="lg:col-span-2 rounded-xl border border-[#E0E0E0] bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col">
        <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-3 block">Create Dispatch Assignment</span>
        
        <form wire:submit.prevent="createDispatch" class="mt-6 space-y-5 flex-1 flex flex-col justify-between">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <!-- Route Selector -->
                <div class="space-y-1">
                    <label for="dispatch-route" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Select Transit Route</label>
                    <select wire:model="selectedRoute" id="dispatch-route" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <option value="">Choose a route...</option>
                        @foreach($routes as $route)
                            <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                        @endforeach
                    </select>
                </div>
 
                <!-- Bus Selector -->
                <div class="space-y-1">
                    <label for="dispatch-bus" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Select Bus Plate</label>
                    <select wire:model="selectedBus" id="dispatch-bus" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <option value="">Choose a bus...</option>
                        @foreach($availableBuses as $bus)
                            <option value="{{ $bus['plate'] }}">{{ $bus['plate'] }} [Ready]</option>
                        @endforeach
                    </select>
                </div>
 
                <!-- Driver Selector -->
                <div class="space-y-1">
                    <label for="dispatch-driver" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Select Driver</label>
                    <select wire:model="selectedDriver" id="dispatch-driver" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <option value="">Choose a driver...</option>
                        @foreach($availableDrivers as $driver)
                            <option value="{{ $driver['name'] }}">{{ $driver['name'] }} [Standby]</option>
                        @endforeach
                    </select>
                </div>
 
                <!-- Departure time field -->
                <div class="space-y-1">
                    <label for="dispatch-time" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Departure Time</label>
                    <input wire:model="departureTime" id="dispatch-time" type="time" required class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                </div>
            </div>
 
            <div class="pt-6 shrink-0 border-t border-slate-100 flex items-center justify-end">
                <button type="submit" class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">
                    Create Dispatch
                </button>
            </div>
        </form>
    </div>
</div>