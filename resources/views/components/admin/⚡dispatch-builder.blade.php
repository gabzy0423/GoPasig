<?php
 
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
 
new class extends Component
{
    // --- Pool data (left panel — filtered, standby only) ---
    public $availableBuses   = [];
    public $availableDrivers = [];
 
    // --- Operational Summary Counters ---
    public $availableBusesCount   = 0;
    public $availableDriversCount = 0;
    public $activeDispatchesCount  = 0;
    public $pendingDispatchesCount = 0;
 
    // --- Full dropdown data (all resources with selectable flags) ---
    public $allBuses   = [];
    public $allDrivers = [];
 
    // --- Routes ---
    public $routes = [];
 
    // --- Form fields (ID-based, not plate/name strings) ---
    public $selectedRoute    = '';
    public $selectedBusId    = '';
    public $selectedDriverId = '';
    public $departureTime    = '';
 
    // --- Search & Confirmation Checkbox ---
    public $busSearch        = '';
    public $driverSearch     = '';
    public $confirmDispatch  = false;
    public $refreshInterval  = 30;
 
    public function mount()
    {
        $this->refreshInterval = (int) \App\Models\SystemSetting::get('dispatch_builder_refresh_interval_seconds', 30);
        $this->loadData();
    }
 
    public function loadData()
    {
        // 1. Fetch routes
        $this->routes = Route::getAllCached()->map(function($r) {
            return [
                'id'   => $r->id,
                'name' => $r->name . ' — ' . $r->description
            ];
        })->toArray();
 
        // 2. Shared query data
        $activeBusIds        = Trip::whereIn('status', ['dispatched', 'ongoing'])->pluck('bus_id')->toArray();
        $activeBusPlates     = Bus::where('status', 'operating')->pluck('plate_number')->toArray();
        $activeBusDrivers    = Bus::where('status', 'operating')->pluck('driver_name')->toArray();
        $activeTripDriverIds = Trip::whereIn('status', ['dispatched', 'ongoing'])->pluck('driver_id')->toArray();
 
        // Eager load all buses and drivers to prevent N+1 queries.
        $allBusesCollection   = Bus::orderBy('plate_number')->get();
        $allDriversCollection = Driver::orderBy('last_name')->orderBy('first_name')->get();
        $busesByPlate         = $allBusesCollection->keyBy('plate_number');
 
        // 3. Available Buses POOL (left panel) — available only, no active/dispatched trip, matches search
        $this->availableBuses = $allBusesCollection->filter(function($bus) use ($activeBusIds) {
            if ($bus->status !== 'available') return false;
            if (in_array($bus->id, $activeBusIds)) return false;
            if ($this->busSearch !== '') {
                return str_contains(strtolower($bus->plate_number), strtolower($this->busSearch));
            }
            return true;
        })->map(function($bus) {
            return [
                'id'     => $bus->id,
                'plate'  => $bus->plate_number,
                'status' => 'Ready',
            ];
        })->values()->toArray();
 
        // 4. Available Drivers POOL (left panel) — standby only, matches search
        $this->availableDrivers = $allDriversCollection->filter(function($driver) use ($activeBusDrivers, $activeBusPlates, $activeTripDriverIds, $busesByPlate) {
            if ($driver->status === 'suspended') return false;
 
            $licenseExpired = now()->greaterThan(
                Carbon::parse($driver->license_expiry)->endOfDay()
            );
            if ($licenseExpired) return false;
 
            // Driver is unavailable if assigned to a broken down bus
            if ($driver->assigned_bus) {
                $assignedBus = $busesByPlate->get($driver->assigned_bus);
                if ($assignedBus && $assignedBus->status === 'breakdown') {
                    return false;
                }
            }
 
            $fullName = $driver->first_name . ' ' . $driver->last_name;
            if ($this->driverSearch !== '') {
                if (!str_contains(strtolower($fullName), strtolower($this->driverSearch))) {
                    return false;
                }
            }
 
            if (in_array($fullName, $activeBusDrivers)) return false;
            if ($driver->assigned_bus && in_array($driver->assigned_bus, $activeBusPlates)) return false;
            if (in_array($driver->id, $activeTripDriverIds)) return false;
 
            return true;
        })->map(function($driver) {
            return [
                'id'     => $driver->id,
                'name'   => $driver->first_name . ' ' . $driver->last_name,
                'status' => 'Standby',
            ];
        })->values()->toArray();
 
        // 5. ALL Buses — for dropdown (selectable flag + label per bus)
        $this->allBuses = $allBusesCollection->map(
            function($bus) use ($activeBusIds) {
                $onTrip = in_array($bus->id, $activeBusIds);
 
                if ($bus->status === 'available' && !$onTrip) {
                    $selectable = true;
                    $label      = 'Ready';
                } elseif ($bus->status === 'ready') {
                    $selectable = false;
                    $label      = 'Dispatched';
                } elseif ($bus->status === 'operating') {
                    $selectable = false;
                    $label      = 'On Trip';
                } elseif ($bus->status === 'maintenance') {
                    $selectable = false;
                    $label      = 'Maintenance';
                } elseif ($bus->status === 'breakdown') {
                    $selectable = false;
                    $label      = 'Breakdown';
                } elseif ($bus->status === 'inactive') {
                    $selectable = false;
                    $label      = 'Inactive';
                } elseif ($onTrip) {
                    $selectable = false;
                    $label      = 'On Trip';
                } else {
                    $selectable = false;
                    $label      = 'Needs Review';
                }
 
                return [
                    'id'              => $bus->id,
                    'plate_number'    => $bus->plate_number,
                    'selectable'      => $selectable,
                    'label'           => $label,
                    'has_observation' => (bool)$bus->has_observation,
                ];
            }
        )->toArray();
 
        // 6. ALL Drivers — for dropdown (selectable flag + label per driver)
        $this->allDrivers = $allDriversCollection->map(
            function($driver) use ($activeBusPlates, $activeTripDriverIds, $busesByPlate) {
                $licenseExpired = now()->greaterThan(
                    Carbon::parse($driver->license_expiry)->endOfDay()
                );
                $onDuty = in_array($driver->id, $activeTripDriverIds)
                    || ($driver->assigned_bus && in_array($driver->assigned_bus, $activeBusPlates));
 
                $hasBreakdown = false;
                if ($driver->assigned_bus) {
                    $assignedBus = $busesByPlate->get($driver->assigned_bus);
                    if ($assignedBus && $assignedBus->status === 'breakdown') {
                        $hasBreakdown = true;
                    }
                }
 
                if ($driver->status === 'suspended') {
                    $selectable = false;
                    $label      = 'Suspended';
                } elseif ($licenseExpired) {
                    $selectable = false;
                    $label      = 'License Expired';
                } elseif ($hasBreakdown) {
                    $selectable = false;
                    $label      = 'Breakdown';
                } elseif ($onDuty) {
                    $selectable = false;
                    $label      = 'On Duty';
                } else {
                    $selectable = true;
                    $label      = 'Standby';
                }
 
                return [
                    'id'         => $driver->id,
                    'full_name'  => $driver->first_name . ' ' . $driver->last_name,
                    'selectable' => $selectable,
                    'label'      => $label,
                ];
            }
        )->toArray();
 
        // 7. Check if currently selected resources became unavailable.
        if ($this->selectedBusId) {
            $selectedBusItem = collect($this->allBuses)->firstWhere('id', $this->selectedBusId);
            if (!$selectedBusItem || !$selectedBusItem['selectable']) {
                $plate = $selectedBusItem ? $selectedBusItem['plate_number'] : 'Selected bus';
                $reason = $selectedBusItem ? $selectedBusItem['label'] : 'removed';
                $this->selectedBusId = '';
                $this->addError('selectedBusId', "The selected bus is no longer available ({$reason}).");
                $this->dispatch('show-toast', ['message' => "Bus {$plate} has become unavailable ({$reason}).", 'type' => 'warning']);
            }
        }
 
        if ($this->selectedDriverId) {
            $selectedDriverItem = collect($this->allDrivers)->firstWhere('id', $this->selectedDriverId);
            if (!$selectedDriverItem || !$selectedDriverItem['selectable']) {
                $name = $selectedDriverItem ? $selectedDriverItem['full_name'] : 'Selected driver';
                $reason = $selectedDriverItem ? $selectedDriverItem['label'] : 'removed';
                $this->selectedDriverId = '';
                $this->addError('selectedDriverId', "The selected driver is no longer available ({$reason}).");
                $this->dispatch('show-toast', ['message' => "Driver {$name} has become unavailable ({$reason}).", 'type' => 'warning']);
            }
        }

        $this->availableBusesCount   = collect($this->allBuses)->where('selectable', true)->count();
        $this->availableDriversCount = collect($this->allDrivers)->where('selectable', true)->count();
        $this->activeDispatchesCount  = Trip::where('status', 'ongoing')->count();
        $this->pendingDispatchesCount = Trip::where('status', 'pending')->count();
    }
 
    #[On('refresh-dispatch-data')]
    public function refreshDispatchData()
    {
        $this->loadData();
    }
 
    public function updatedBusSearch()
    {
        $this->loadData();
    }
 
    public function updatedDriverSearch()
    {
        $this->loadData();
    }
 
    public function updatedSelectedBusId()
    {
        $this->resetErrorBag('selectedBusId');
    }
 
    public function updatedSelectedDriverId()
    {
        $this->resetErrorBag('selectedDriverId');
    }
 
    public function updatedSelectedRoute()
    {
        $this->resetErrorBag('selectedRoute');
    }
 
    public function updatedDepartureTime()
    {
        $this->resetErrorBag('departureTime');
    }
 
    public function updatedConfirmDispatch()
    {
        $this->resetErrorBag('confirmDispatch');
    }
 
    public function createDispatch()
    {
        $this->resetErrorBag();
 
        $validator = \Illuminate\Support\Facades\Validator::make([
            'selectedRoute'    => $this->selectedRoute,
            'selectedBusId'    => $this->selectedBusId,
            'selectedDriverId' => $this->selectedDriverId,
            'departureTime'    => $this->departureTime,
            'confirmDispatch'  => $this->confirmDispatch,
        ], [
            'selectedRoute'    => 'required|exists:routes,id',
            'selectedBusId'    => 'required|exists:buses,id',
            'selectedDriverId' => 'required|exists:drivers,id',
            'departureTime'    => 'required',
            'confirmDispatch'  => 'accepted',
        ], [
            'confirmDispatch.accepted' => 'You must confirm the dispatch details.',
        ]);
 
        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
            $this->dispatch('validation-failed', ['errors' => $validator->errors()->keys()]);
            return;
        }
 
        $bus    = Bus::findOrFail($this->selectedBusId);
        $driver = Driver::findOrFail($this->selectedDriverId);
 
        try {
            $route = Route::findOrFail($this->selectedRoute);
 
            \App\Services\SimulationDispatchService::dispatch(
                $bus,
                $driver,
                $route,
                Auth::id() ?: 1,
                'Central Dispatch'
            );
        } catch (\App\Exceptions\DispatchException $e) {
            $this->addError('dispatchError', $e->getMessage());
            $this->dispatch('show-toast', ['message' => 'Dispatch failed: ' . $e->getMessage(), 'type' => 'error']);
            return;
        } catch (\Exception $e) {
            $this->addError('dispatchError', 'Dispatch failed: ' . $e->getMessage());
            $this->dispatch('show-toast', ['message' => 'Dispatch failed: ' . $e->getMessage(), 'type' => 'error']);
            return;
        }
        $this->dispatch('dispatchSuccessful');
 
        $this->reset(['selectedRoute', 'selectedBusId', 'selectedDriverId', 'departureTime', 'confirmDispatch']);
        $this->loadData();
    }
};
?>
 
<div x-data="{ 
    busesOpen: true, 
    driversOpen: true,
    routeSelected: @entangle('selectedRoute'),
    busSelected: @entangle('selectedBusId'),
    driverSelected: @entangle('selectedDriverId'),
    timeSelected: @entangle('departureTime'),
    confirmSelected: @entangle('confirmDispatch'),
    checkScroll() {
        if (this.routeSelected && this.busSelected && this.driverSelected && this.timeSelected) {
            const previewEl = document.getElementById('dispatch-preview-section');
            if (previewEl && window.innerWidth < 1024) {
                previewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
}" 
x-init="
    init();
    $watch('routeSelected', value => checkScroll());
    $watch('busSelected', value => checkScroll());
    $watch('driverSelected', value => checkScroll());
    $watch('timeSelected', value => checkScroll());
" 
wire:poll.visible.{{ $refreshInterval }}s="refreshDispatchData">

    <!-- Operational Overview (Summary Row) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-6 select-none animate-fade-in">
        <!-- Card 1: Available Buses -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Available Buses</span>
                <div class="h-6 w-6 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                    <i class="ti ti-bus text-sm"></i>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-[20px] font-black text-slate-900 leading-none">{{ $availableBusesCount }}</span>
                <span class="text-[9px] text-slate-500 font-semibold truncate">Ready standby fleet</span>
            </div>
        </div>

        <!-- Card 2: Available Drivers -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Available Drivers</span>
                <div class="h-6 w-6 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                    <i class="ti ti-id text-sm"></i>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-[20px] font-black text-slate-900 leading-none">{{ $availableDriversCount }}</span>
                <span class="text-[9px] text-slate-500 font-semibold truncate">Standby ready crew</span>
            </div>
        </div>

        <!-- Card 3: Active Dispatches -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 border-l-[3px] border-l-[#003F87]">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Active Dispatches</span>
                <div class="h-6 w-6 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                    <i class="ti ti-route text-sm"></i>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-[20px] font-black text-slate-900 leading-none">{{ $activeDispatchesCount }}</span>
                <span class="text-[9px] text-slate-500 font-semibold truncate">Buses on route right now</span>
            </div>
        </div>

        <!-- Card 4: Pending Dispatches -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 border-l-[3px] border-l-[#BA7517]">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Pending Dispatches</span>
                <div class="h-6 w-6 rounded bg-amber-50 flex items-center justify-center text-[#BA7517]">
                    <i class="ti ti-clock text-sm"></i>
                </div>
            </div>
            <div class="mt-1 flex items-baseline gap-1.5">
                <span class="text-[20px] font-black text-slate-900 leading-none">{{ $pendingDispatchesCount }}</span>
                <span class="text-[9px] text-slate-500 font-semibold truncate">Awaiting start time</span>
            </div>
        </div>
    </div>

    <!-- Main Workspace Layout -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Left Side: Pool of Available Resources -->
        <div class="lg:col-span-1 space-y-6 flex flex-col">
            <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col select-none">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2.5 shrink-0 block">Available Resources</span>
                
                <div class="space-y-4 mt-4 flex-1">
                    <!-- Collapsible Buses Section -->
                    <div class="border border-slate-100 rounded-lg p-2 bg-slate-50/20">
                        <button type="button" @click="busesOpen = !busesOpen" class="w-full flex items-center justify-between text-xs font-bold text-slate-700 pb-2 px-1 border-none bg-transparent cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-bus text-slate-500 text-sm"></i> Standby Buses ({{ count($availableBuses) }})
                            </span>
                            <i class="ti text-xs text-slate-450" :class="busesOpen ? 'ti-chevron-down' : 'ti-chevron-right'"></i>
                        </button>
                        
                        <div x-show="busesOpen" x-collapse class="space-y-2 mt-2 max-h-[190px] overflow-y-auto scrollbar-thin px-1">
                            @forelse($availableBuses as $bus)
                                <div wire:click="$set('selectedBusId', {{ $bus['id'] }})" 
                                     class="flex items-center justify-between border px-3 py-2 rounded-lg cursor-pointer transition {{ $selectedBusId == $bus['id'] ? 'border-[#003F87] bg-blue-50/50 shadow-sm' : 'border-slate-100 bg-white hover:border-[#003F87]/20 hover:shadow-sm' }}">
                                    <div class="flex items-center gap-2">
                                        <i class="ti ti-bus text-slate-400 text-xs"></i>
                                        <span class="text-xs font-extrabold text-slate-800">{{ $bus['plate'] }}</span>
                                    </div>
                                    <span class="flex items-center gap-1 text-[9px] font-bold text-[#639922] bg-[#E8F4E0] px-2 py-0.5 rounded uppercase">
                                        <i class="ti ti-circle-check text-[10px]"></i> {{ $bus['status'] }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-[10px] text-slate-500 py-4 px-2 leading-relaxed text-center">
                                    No standby buses available. Complete maintenance or release a vehicle to continue.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Collapsible Drivers Section -->
                    <div class="border border-slate-100 rounded-lg p-2 bg-slate-50/20">
                        <button type="button" @click="driversOpen = !driversOpen" class="w-full flex items-center justify-between text-xs font-bold text-slate-700 pb-2 px-1 border-none bg-transparent cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-id text-slate-500 text-sm"></i> Standby Drivers ({{ count($availableDrivers) }})
                            </span>
                            <i class="ti text-xs text-slate-450" :class="driversOpen ? 'ti-chevron-down' : 'ti-chevron-right'"></i>
                        </button>
                        
                        <div x-show="driversOpen" x-collapse class="space-y-2 mt-2 max-h-[190px] overflow-y-auto scrollbar-thin px-1">
                            @forelse($availableDrivers as $driver)
                                <div wire:click="$set('selectedDriverId', {{ $driver['id'] }})"
                                     class="flex items-center justify-between border px-3 py-2 rounded-lg cursor-pointer transition {{ $selectedDriverId == $driver['id'] ? 'border-[#003F87] bg-blue-50/50 shadow-sm' : 'border-slate-100 bg-white hover:border-[#003F87]/20 hover:shadow-sm' }}">
                                    <div class="flex items-center gap-2">
                                        <i class="ti ti-id text-slate-400 text-xs"></i>
                                        <span class="text-xs font-extrabold text-slate-800">{{ $driver['name'] }}</span>
                                    </div>
                                    <span class="flex items-center gap-1 text-[9px] font-bold text-[#639922] bg-[#E8F4E0] px-2 py-0.5 rounded uppercase">
                                        <i class="ti ti-circle-check text-[10px]"></i> {{ $driver['status'] }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-[10px] text-slate-500 py-4 px-2 leading-relaxed text-center">
                                    No standby drivers available. Assign or release a driver to continue.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
     
        <!-- Right Side: Guided Assignment Form -->
        <div class="lg:col-span-2 rounded-xl border border-[#E0E0E0] bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Create Dispatch Assignment</span>
                
                <div class="flex items-center gap-2">
                    <!-- Loading Spinner -->
                    <div wire:loading wire:target="refreshDispatchData" class="flex items-center gap-1">
                        <svg class="animate-spin h-3.5 w-3.5 text-[#003F87]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <!-- Refresh button -->
                    <button type="button" wire:click="refreshDispatchData" wire:loading.attr="disabled"
                        class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-[#003F87] transition cursor-pointer bg-transparent border-none outline-none"
                        title="Refresh standby resources lists">
                        <i class="ti ti-refresh text-sm"></i>
                        <span class="uppercase tracking-wider">Refresh</span>
                    </button>
                </div>
            </div>
            
            <!-- Screen reader live region for accessibility announcements -->
            <div id="sr-announcer" class="sr-only" aria-live="polite"></div>
     
            <form wire:submit.prevent="createDispatch" class="mt-6 flex-1 flex flex-col justify-between">
                
                <!-- General error banner -->
                @if($errors->has('dispatchError'))
                    <div class="text-xs text-red-700 bg-red-50 border border-red-100 p-3 rounded-lg flex items-start gap-2 animate-fade-in mb-6">
                        <i class="ti ti-alert-circle text-base shrink-0 mt-0.5 text-red-500"></i>
                        <div>
                            <p class="font-bold">Dispatch Assignment Failed</p>
                            <p class="mt-0.5 font-medium">{{ $errors->first('dispatchError') }}</p>
                        </div>
                    </div>
                @endif
     
                <div class="space-y-6">
                    <!-- 1. Route Section -->
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] mb-3">1. Route Assignment</h3>
                        <div class="space-y-1">
                            <label for="dispatch-route" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Transit Route</label>
                            <select wire:model.live="selectedRoute" id="dispatch-route" required tabindex="1"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedRoute') ? 'true' : 'false' }}"
                                :class="!routeSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="">Choose a route...</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                                @endforeach
                            </select>
                            @error('selectedRoute')
                                <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
     
                    <!-- 2. Vehicle Section -->
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] mb-3">2. Vehicle Assignment</h3>
                        <div class="space-y-1">
                            <label for="dispatch-bus" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Select Bus</label>
                            <select wire:model.live="selectedBusId" id="dispatch-bus" required tabindex="2"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedBusId') ? 'true' : 'false' }}"
                                :class="routeSelected && !busSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="">Choose a bus...</option>
                                <optgroup label="Available">
                                    @foreach(collect($allBuses)->where('selectable', true) as $bus)
                                        <option value="{{ $bus['id'] }}">
                                            {{ $bus['plate_number'] }} [{{ $bus['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Hindi Available" class="text-slate-400">
                                    @foreach(collect($allBuses)->where('selectable', false) as $bus)
                                        <option value="{{ $bus['id'] }}" disabled>
                                            {{ $bus['plate_number'] }} [{{ $bus['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('selectedBusId')
                                <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                            @php
                                $selBus = collect($allBuses)->firstWhere('id', $selectedBusId);
                            @endphp
                            @if($selBus && !empty($selBus['has_observation']))
                                <div class="text-[10.5px] text-amber-700 bg-amber-50 border border-amber-200/50 p-2.5 rounded-lg flex items-start gap-2 mt-1.5 animate-fade-in font-bold">
                                    <i class="ti ti-alert-triangle text-xs shrink-0 mt-0.5 text-amber-655"></i>
                                    <span>Warning: This bus passed inspection with observations. Review before dispatching.</span>
                                </div>
                            @endif
                        </div>
                    </div>
     
                    <!-- 3. Personnel Section -->
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] mb-3">3. Personnel Assignment</h3>
                        <div class="space-y-1">
                            <label for="dispatch-driver" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Select Driver</label>
                            <select wire:model.live="selectedDriverId" id="dispatch-driver" required tabindex="3"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedDriverId') ? 'true' : 'false' }}"
                                :class="busSelected && !driverSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="">Choose a driver...</option>
                                <optgroup label="Available">
                                    @foreach(collect($allDrivers)->where('selectable', true) as $driver)
                                        <option value="{{ $driver['id'] }}">
                                            {{ $driver['full_name'] }} [{{ $driver['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Hindi Available" class="text-slate-400">
                                    @foreach(collect($allDrivers)->where('selectable', false) as $driver)
                                        <option value="{{ $driver['id'] }}" disabled>
                                            {{ $driver['full_name'] }} [{{ $driver['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('selectedDriverId')
                                <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
     
                    <!-- 4. Schedule Section -->
                    <div class="pb-2">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] mb-3">4. Schedule</h3>
                        <div class="space-y-1">
                            <label for="dispatch-time" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Departure Time</label>
                            <input wire:model.live="departureTime" id="dispatch-time" type="time" required tabindex="4"
                                aria-required="true" aria-invalid="{{ $errors->has('departureTime') ? 'true' : 'false' }}"
                                :class="driverSelected && !timeSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                            @error('departureTime')
                                <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Live Dispatch Preview Panel -->
                <div id="dispatch-preview-section" class="mt-6 rounded-xl border border-slate-200 bg-slate-50/50 p-5 select-none animate-fade-in">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] block mb-3">Live Dispatch Preview</span>
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <!-- Selected Route -->
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Selected Route</span>
                            <span class="font-bold text-slate-800 mt-0.5 block">
                                @if($selectedRoute)
                                    {{ collect($routes)->firstWhere('id', $selectedRoute)['name'] ?? 'Route Selected' }}
                                @else
                                    <span class="text-slate-450 italic font-semibold">No Route Selected</span>
                                @endif
                            </span>
                        </div>

                        <!-- Departure Time -->
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Departure Time</span>
                            <span class="font-bold text-slate-800 mt-0.5 block">
                                @if($departureTime)
                                    {{ Carbon::parse($departureTime)->format('h:i A') }}
                                @else
                                    <span class="text-slate-450 italic font-semibold">No Time Specified</span>
                                @endif
                            </span>
                        </div>

                        <!-- Selected Bus -->
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Selected Bus</span>
                            <span class="font-bold text-slate-800 mt-0.5 block">
                                @if($selectedBusId)
                                    @php $busObj = collect($allBuses)->firstWhere('id', $selectedBusId); @endphp
                                    {{ $busObj['plate_number'] ?? 'Bus Selected' }}
                                    <span class="inline-flex rounded-full bg-blue-50 text-[#003F87] border border-blue-100 px-2 py-0.2 text-[8px] font-bold uppercase tracking-wider ml-1">
                                        {{ $busObj['label'] ?? 'Standby' }}
                                    </span>
                                @else
                                    <span class="text-slate-450 italic font-semibold">No Bus Selected</span>
                                @endif
                            </span>
                        </div>

                        <!-- Selected Driver -->
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Selected Driver</span>
                            <span class="font-bold text-slate-800 mt-0.5 block">
                                @if($selectedDriverId)
                                    @php $driverObj = collect($allDrivers)->firstWhere('id', $selectedDriverId); @endphp
                                    {{ $driverObj['full_name'] ?? 'Driver Selected' }}
                                    <span class="inline-flex rounded-full bg-emerald-50 text-[#639922] border border-emerald-100 px-2 py-0.2 text-[8px] font-bold uppercase tracking-wider ml-1">
                                        {{ $driverObj['label'] ?? 'Standby' }}
                                    </span>
                                @else
                                    <span class="text-slate-450 italic font-semibold">No Driver Selected</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Readiness Checklist Panel (Mirrors Backend Rules) -->
                @php
                    $routeOk = !empty($selectedRoute);
                    $busOk = !empty($selectedBusId) && (collect($allBuses)->firstWhere('id', $selectedBusId)['selectable'] ?? false);
                    $driverOk = !empty($selectedDriverId) && (collect($allDrivers)->firstWhere('id', $selectedDriverId)['selectable'] ?? false);
                    $timeOk = !empty($departureTime);
                    $confirmOk = (bool)$confirmDispatch;

                    $allValid = $routeOk && $busOk && $driverOk && $timeOk && $confirmOk;
                @endphp
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 select-none">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 block mb-3">Dispatch Readiness Checklist</span>
                    <div class="grid grid-cols-2 gap-3.5 text-[11px] font-semibold text-slate-700">
                        <!-- Route Check -->
                        <div class="flex items-center gap-2">
                            @if($routeOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Route selected</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Route selected</span>
                            @endif
                        </div>

                        <!-- Bus Check -->
                        <div class="flex items-center gap-2">
                            @if($busOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Bus assigned</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Bus assigned</span>
                            @endif
                        </div>

                        <!-- Driver Check -->
                        <div class="flex items-center gap-2">
                            @if($driverOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Driver assigned</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Driver assigned</span>
                            @endif
                        </div>

                        <!-- Departure Time Check -->
                        <div class="flex items-center gap-2">
                            @if($timeOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Departure time selected</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Departure time selected</span>
                            @endif
                        </div>

                        <!-- Confirmation Checkbox Check -->
                        <div class="flex items-center gap-2 col-span-2">
                            @if($confirmOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Confirmation checked</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Confirmation checked</span>
                            @endif
                        </div>
                    </div>

                    @if($allValid)
                        <div class="mt-4 text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-100 p-2.5 rounded-lg flex items-center gap-2 animate-fade-in font-bold">
                            <i class="ti ti-circle-check-filled text-base shrink-0"></i>
                            <span>Ready for dispatch. All required information has been validated.</span>
                        </div>
                    @else
                        <div class="mt-4 text-[11px] text-amber-700 bg-amber-50 border border-amber-100 p-2.5 rounded-lg flex items-start gap-2 animate-fade-in font-bold">
                            <i class="ti ti-alert-triangle text-base shrink-0 mt-0.5 text-amber-655"></i>
                            <div>
                                <span>Dispatch is not yet ready. Complete the remaining checklist requirements.</span>
                            </div>
                        </div>
                    @endif
                </div>
     
                <!-- Confirmation Checkbox Input -->
                <div class="pt-4 border-t border-slate-100 space-y-1 mt-4">
                    <div class="flex items-start gap-2.5">
                        <input type="checkbox" id="confirm-dispatch" wire:model.live="confirmDispatch" required tabindex="5"
                            aria-required="true" aria-invalid="{{ $errors->has('confirmDispatch') ? 'true' : 'false' }}"
                            :class="timeSelected && !confirmSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20' : 'border-slate-200'"
                            class="h-4 w-4 rounded text-[#003F87] focus:ring-[#003F87]/20 mt-0.5 cursor-pointer">
                        <label for="confirm-dispatch" class="text-xs font-semibold text-slate-600 select-none cursor-pointer">
                            I confirm that the selected bus and driver are fit for service and assigned route is correct.
                        </label>
                    </div>
                    @error('confirmDispatch')
                        <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>
     
                <!-- Submit Area -->
                <div class="pt-5 shrink-0 border-t border-slate-100 flex items-center justify-end mt-4">
                    <button type="submit" @if(!$allValid) disabled @endif tabindex="6" wire:loading.attr="disabled"
                        class="flex items-center gap-2 rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] active:scale-[0.98] transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed border-none shadow-sm">
                        <span wire:loading.remove>Create Dispatch</span>
                        <span wire:loading class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <!-- Toast Container -->
    <div x-data="{
        toasts: [],
        addToast(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, 5000);
        }
    }"
    x-on:show-toast.window="addToast($event.detail[0].message, $event.detail[0].type)"
    class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="transition ease-out duration-300 transform translate-y-2 opacity-0"
                 x-transition:enter-start="transform translate-y-2 opacity-0"
                 x-transition:enter-end="transform translate-y-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200 opacity-0"
                 class="pointer-events-auto flex items-start gap-3 rounded-xl border p-4 shadow-lg backdrop-blur-md"
                 :class="{
                     'bg-emerald-500/90 text-white border-emerald-500/10': toast.type === 'success',
                     'bg-amber-500/95 text-white border-amber-500/10': toast.type === 'warning',
                     'bg-rose-500/95 text-white border-rose-500/10': toast.type === 'error'
                 }">
                <i class="text-lg shrink-0 mt-0.5"
                   :class="{
                       'ti ti-circle-check': toast.type === 'success',
                       'ti ti-alert-triangle': toast.type === 'warning',
                       'ti ti-alert-circle': toast.type === 'error'
                   }"></i>
                <div class="flex-1 text-xs font-bold leading-normal" x-text="toast.message"></div>
                <button type="button" @click="toasts = toasts.filter(t => t.id !== toast.id)" class="text-white/80 hover:text-white shrink-0">
                    <i class="ti ti-x text-sm"></i>
                </button>
            </div>
        </template>
    </div>
 
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.dispatchBuilder !== 'function') {
                window.dispatchBuilder = function() {
                    return {
                        init() {
                            window.addEventListener('beforeunload', (e) => {
                                if (this.isDirty()) {
                                    e.preventDefault();
                                    e.returnValue = 'You have unsaved changes in the Dispatch Builder. Are you sure you want to leave?';
                                }
                            });
     
                            const originalSwitchScreen = window.switchScreen;
                            window.switchScreen = (screenName) => {
                                if (screenName !== 'dispatch' && this.isDirty()) {
                                    if (!confirm('You have unsaved changes in the Dispatch Builder. Are you sure you want to navigate away?')) {
                                        return;
                                    }
                                }
                                if (typeof originalSwitchScreen === 'function') {
                                    originalSwitchScreen(screenName);
                                }
                            };
     
                            window.addEventListener('validation-failed', (e) => {
                                const errors = e.detail[0]?.errors || [];
                                if (errors.length > 0) {
                                    const fieldIdMap = {
                                        'selectedRoute': 'dispatch-route',
                                        'selectedBusId': 'dispatch-bus',
                                        'selectedDriverId': 'dispatch-driver',
                                        'departureTime': 'dispatch-time',
                                        'confirmDispatch': 'confirm-dispatch'
                                    };
                                    const firstErrorField = errors[0];
                                    const id = fieldIdMap[firstErrorField];
                                    if (id) {
                                        const el = document.getElementById(id);
                                        if (el) {
                                            el.focus();
                                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        }
                                    }
                                    const announcer = document.getElementById('sr-announcer');
                                    if (announcer) {
                                        announcer.textContent = 'Validation failed. Please correct the invalid fields: ' + errors.join(', ');
                                    }
                                }
                            });
     
                            window.addEventListener('dispatchSuccessful', () => {
                                setTimeout(() => {
                                    const routeEl = document.getElementById('dispatch-route');
                                    if (routeEl) routeEl.focus();
                                }, 100);
                                
                                this.$dispatch('show-toast', { message: 'Bus successfully dispatched on route!', type: 'success' });
                                
                                const announcer = document.getElementById('sr-announcer');
                                if (announcer) {
                                    announcer.textContent = 'Bus successfully dispatched. The form has been reset.';
                                }
                            });
                        },
                        isDirty() {
                            if (!this.$wire) return false;
                            const r = this.$wire.get('selectedRoute');
                            const b = this.$wire.get('selectedBusId');
                            const d = this.$wire.get('selectedDriverId');
                            const t = this.$wire.get('departureTime');
                            const c = this.$wire.get('confirmDispatch');
                            return (r && r !== '') ||
                                   (b && b !== '') ||
                                   (d && d !== '') ||
                                   (t && t !== '') ||
                                   c;
                        }
                    };
                };
            }
        });
    </script>
</div>
