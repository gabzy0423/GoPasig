<?php
 
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Services\RouteVariantSelectionService;
use App\Services\CentralDispatchEligibilityService;
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
    public $routeVariants = [];
 
    // --- Form fields (ID-based, not plate/name strings) ---
    public $selectedRoute    = '';
    public $selectedRouteVariant = '';
    public $selectedBusId    = '';
    public $selectedDriverId = '';
 
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

    public function placeholder()
    {
        return <<<'HTML'
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3 text-sm font-semibold text-slate-600">
                <i class="ti ti-loader-2 animate-spin text-lg text-[#003F87]"></i>
                <span>Preparing Central Dispatch...</span>
            </div>
        </div>
        HTML;
    }
 
    public function loadData()
    {
        // 1. Fetch routes
        $routeVariantSelection = app(RouteVariantSelectionService::class);
        $variantsByRoute = RouteVariant::withCount('stops')->get()->groupBy('route_id');

        $this->routes = Route::getCanonicalProductionCached()->map(function($r) use ($variantsByRoute, $routeVariantSelection) {
            $variants = ($variantsByRoute->get($r->id, collect()))->map(fn ($variant) => [
                'id' => $variant->id,
                'route_id' => $variant->route_id,
                'label' => $routeVariantSelection->label($variant),
                'direction' => $variant->direction,
                'geometry_status' => $variant->geometry_status,
                'is_default' => (bool) $variant->is_default,
                'usable_for_dispatch' => $routeVariantSelection->isUsableForLiveDispatch($variant),
            ])->values()->toArray();

            return [
                'id'   => $r->id,
                'name' => $r->name . ' — ' . $r->description,
                'variants' => $variants,
            ];
        })->toArray();

        $this->routeVariants = collect($this->routes)->mapWithKeys(fn ($route) => [$route['id'] => $route['variants']])->toArray();
 
        // 2. Shared query data
        $activeBusIds        = Trip::whereIn('status', ['dispatched', 'ongoing'])->pluck('bus_id')->toArray();
        $activeBusPlates     = Bus::where('status', 'operating')->pluck('plate_number')->toArray();
        $activeBusDrivers    = Bus::where('status', 'operating')->pluck('driver_name')->toArray();
        $activeTripDriverIds = Trip::whereIn('status', ['dispatched', 'ongoing'])->pluck('driver_id')->toArray();
 
        // Eager load all buses and drivers to prevent N+1 queries.
        $allBusesCollection   = Bus::orderBy('plate_number')->get();
        $allDriversCollection = Driver::orderBy('last_name')->orderBy('first_name')->get();
        $busesByPlate         = $allBusesCollection->keyBy('plate_number'); 
        // 3. Available Buses POOL (left panel) - Central Dispatch eligible only.
        $this->availableBuses = $allBusesCollection->filter(function($bus) {
            if (! CentralDispatchEligibilityService::busIsEligible($bus)) return false;
            if ($this->busSearch !== '') {
                return str_contains(strtolower($bus->plate_number), strtolower($this->busSearch));
            }
            return true;
        })->map(function($bus) {
            return [
                'id'     => $bus->id,
                'plate'  => $bus->plate_number,
                'status' => 'Dispatchable',
            ];
        })->values()->toArray(); 
        // 4. Available Drivers POOL (left panel) - Central Dispatch eligible only.
        $this->availableDrivers = $allDriversCollection->filter(function($driver) {
            if (! CentralDispatchEligibilityService::driverIsEligible($driver)) return false;
 
            $fullName = $driver->first_name . ' ' . $driver->last_name;
            if ($this->driverSearch !== '') {
                if (!str_contains(strtolower($fullName), strtolower($this->driverSearch))) {
                    return false;
                }
            }
 
            return true;
        })->map(function($driver) {
            return [
                'id'     => $driver->id,
                'name'   => $driver->first_name . ' ' . $driver->last_name,
                'status' => 'Dispatchable',
            ];
        })->values()->toArray(); 
        // 5. ALL Buses - for dropdown (selectable flag + label per bus)
        $this->allBuses = $allBusesCollection->map(
            function($bus) {
                $eligibility = CentralDispatchEligibilityService::bus($bus);
                $selectable = $eligibility['eligible'];
                $label = $selectable ? 'Dispatchable' : $eligibility['reason'];
 
                return [
                    'id'              => $bus->id,
                    'plate_number'    => $bus->plate_number,
                    'selectable'      => $selectable,
                    'label'           => $label,
                    'has_observation' => (bool)$bus->has_observation,
                ];
            }
        )->toArray(); 
        // 6. ALL Drivers - for dropdown (selectable flag + label per driver)
        $this->allDrivers = $allDriversCollection->map(
            function($driver) {
                $eligibility = CentralDispatchEligibilityService::driver($driver);
                $selectable = $eligibility['eligible'];
                $label = $selectable ? 'Dispatchable' : $eligibility['reason'];
 
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
        $this->loadData();
    }
 
    public function updatedSelectedDriverId()
    {
        $this->resetErrorBag('selectedDriverId');
        $this->loadData();
    }
 
    public function updatedSelectedRoute()
    {
        $this->resetErrorBag('selectedRoute');
        $this->resetErrorBag('selectedRouteVariant');
        $this->selectedRouteVariant = '';
        $this->loadData();

        $usableVariants = collect($this->routeVariants[$this->selectedRoute] ?? [])->where('usable_for_dispatch', true)->values();
        $defaultVariant = $usableVariants->firstWhere('is_default', true);

        if ($defaultVariant) {
            $this->selectedRouteVariant = (string) $defaultVariant['id'];
        } elseif ($usableVariants->count() === 1) {
            $this->selectedRouteVariant = (string) $usableVariants->first()['id'];
        }
    }

    public function updatedSelectedRouteVariant()
    {
        $this->resetErrorBag('selectedRouteVariant');
        $this->loadData();
    }
 
    public function updatedConfirmDispatch()
    {
        $this->resetErrorBag('confirmDispatch');
        $this->loadData();
    }
 
    public function createDispatch()
    {
        $this->resetErrorBag();
 
        $validator = \Illuminate\Support\Facades\Validator::make([
            'selectedRoute'    => $this->selectedRoute,
            'selectedRouteVariant' => $this->selectedRouteVariant ?: null,
            'selectedBusId'    => $this->selectedBusId,
            'selectedDriverId' => $this->selectedDriverId,
            'confirmDispatch'  => $this->confirmDispatch,
        ], [
            'selectedRoute'    => 'required|exists:routes,id',
            'selectedRouteVariant' => 'nullable|exists:route_variants,id',
            'selectedBusId'    => 'required|exists:buses,id',
            'selectedDriverId' => 'required|exists:drivers,id',
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
            $routeVariant = $this->selectedRouteVariant ? RouteVariant::findOrFail($this->selectedRouteVariant) : null;
 
            \App\Services\SimulationDispatchService::dispatch(
                $bus,
                $driver,
                $route,
                Auth::id() ?: 1,
                'Central Dispatch',
                $routeVariant
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
 
        $this->reset(['selectedRoute', 'selectedRouteVariant', 'selectedBusId', 'selectedDriverId', 'confirmDispatch']);
        $this->loadData();
    }
};
?>
 
<div x-data="{ 
    busesOpen: true, 
    driversOpen: true,
    dispatchRefreshInFlight: false,
    dispatchRefreshTimer: null,
    routeSelected: @entangle('selectedRoute'),
    routeVariantSelected: @entangle('selectedRouteVariant'),
    busSelected: @entangle('selectedBusId'),
    driverSelected: @entangle('selectedDriverId'),
    confirmSelected: @entangle('confirmDispatch'),
    init() {
        this.startDispatchRefreshPolling();
    },
    isDispatchVisible() {
        const screen = document.getElementById('screen-dispatch');
        return !!screen && !screen.classList.contains('hidden') && document.visibilityState !== 'hidden';
    },
    async requestDispatchRefresh() {
        if (this.dispatchRefreshInFlight) return;
        this.dispatchRefreshInFlight = true;
        try {
            await this.$wire.refreshDispatchData();
        } catch (error) {
            console.error('Dispatch runtime refresh failed:', error);
        } finally {
            this.dispatchRefreshInFlight = false;
        }
    },
    startDispatchRefreshPolling() {
        if (this.dispatchRefreshTimer) return;
        this.dispatchRefreshTimer = setInterval(() => {
            if (this.isDispatchVisible()) this.requestDispatchRefresh();
        }, {{ (int) $refreshInterval * 1000 }});
    },
    checkScroll() {
        if (this.routeSelected && this.busSelected && this.driverSelected) {
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
    $watch('routeVariantSelected', value => checkScroll());
    $watch('busSelected', value => checkScroll());
    $watch('driverSelected', value => checkScroll());
" 
x-on:request-dispatch-runtime-refresh.window="requestDispatchRefresh()">

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
                <span class="text-[9px] text-slate-500 font-semibold truncate">Free standby fleet</span>
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
                <span class="text-[9px] text-slate-500 font-semibold truncate">Available crew</span>
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
                                <i class="ti ti-bus text-slate-500 text-sm"></i> Dispatchable Buses ({{ count($availableBuses) }})
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
                                    No dispatchable buses available. Complete maintenance or release a vehicle to continue.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Collapsible Drivers Section -->
                    <div class="border border-slate-100 rounded-lg p-2 bg-slate-50/20">
                        <button type="button" @click="driversOpen = !driversOpen" class="w-full flex items-center justify-between text-xs font-bold text-slate-700 pb-2 px-1 border-none bg-transparent cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-id text-slate-500 text-sm"></i> Dispatchable Drivers ({{ count($availableDrivers) }})
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
                                    No dispatchable drivers available. Assign or release a driver to continue.
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
                    <div x-show="dispatchRefreshInFlight" x-cloak class="flex items-center gap-1">
                        <svg class="animate-spin h-3.5 w-3.5 text-[#003F87]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <!-- Refresh button -->
                    <button type="button" @click="requestDispatchRefresh()" :disabled="dispatchRefreshInFlight"
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
                            <select wire:change="$set('selectedRoute', $event.target.value)" id="dispatch-route" required tabindex="1"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedRoute') ? 'true' : 'false' }}"
                                :class="!routeSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="" @selected($selectedRoute === '')>Choose a route...</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route['id'] }}" @selected((string) $selectedRoute === (string) $route['id'])>{{ $route['name'] }}</option>
                                @endforeach
                            </select>
                            @error('selectedRoute')
                                <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @php
                            $selectedRouteVariants = collect($routeVariants[$selectedRoute] ?? []);
                        @endphp
                        @if($selectedRouteVariants->isNotEmpty())
                            <div class="space-y-1 mt-3">
                                <label for="dispatch-route-variant" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Direction</label>
                                <select wire:change="$set('selectedRouteVariant', $event.target.value)" id="dispatch-route-variant" tabindex="2"
                                    aria-invalid="{{ $errors->has('selectedRouteVariant') ? 'true' : 'false' }}"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer bg-slate-50">
                                    <option value="" @selected($selectedRouteVariant === '')>{{ $selectedRouteVariants->where('usable_for_dispatch', true)->count() > 1 ? 'Choose a direction...' : 'Use default direction' }}</option>
                                    @foreach($selectedRouteVariants as $variant)
                                        <option value="{{ $variant['id'] }}" @selected((string) $selectedRouteVariant === (string) $variant['id']) @disabled(! $variant['usable_for_dispatch'])>
                                            {{ $variant['label'] }}{{ $variant['usable_for_dispatch'] ? '' : ' (' . $variant['geometry_status'] . ')' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('selectedRouteVariant')
                                    <p class="text-[10px] text-red-500 font-semibold mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
     
                    <!-- 2. Vehicle Section -->
                    <div class="border-b border-slate-100 pb-4">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] mb-3">2. Vehicle Assignment</h3>
                        <div class="space-y-1">
                            <label for="dispatch-bus" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Select Bus</label>
                            <select wire:change="$set('selectedBusId', $event.target.value)" id="dispatch-bus" required tabindex="2"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedBusId') ? 'true' : 'false' }}"
                                :class="routeSelected && !busSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="" @selected($selectedBusId === '')>Choose a bus...</option>
                                <optgroup label="Available">
                                    @foreach(collect($allBuses)->where('selectable', true) as $bus)
                                        <option value="{{ $bus['id'] }}" @selected((string) $selectedBusId === (string) $bus['id'])>
                                            {{ $bus['plate_number'] }} [{{ $bus['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Hindi Available" class="text-slate-400">
                                    @foreach(collect($allBuses)->where('selectable', false) as $bus)
                                        <option value="{{ $bus['id'] }}" @selected((string) $selectedBusId === (string) $bus['id']) disabled>
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
                            <select wire:change="$set('selectedDriverId', $event.target.value)" id="dispatch-driver" required tabindex="3"
                                aria-required="true" aria-invalid="{{ $errors->has('selectedDriverId') ? 'true' : 'false' }}"
                                :class="busSelected && !driverSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20 bg-white' : 'bg-slate-50'"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-2 focus:ring-[#003F87]/20 cursor-pointer">
                                <option value="" @selected($selectedDriverId === '')>Choose a driver...</option>
                                <optgroup label="Available">
                                    @foreach(collect($allDrivers)->where('selectable', true) as $driver)
                                        <option value="{{ $driver['id'] }}" @selected((string) $selectedDriverId === (string) $driver['id'])>
                                            {{ $driver['full_name'] }} [{{ $driver['label'] }}]
                                        </option>
                                    @endforeach
                                </optgroup>
                                <optgroup label="Hindi Available" class="text-slate-400">
                                    @foreach(collect($allDrivers)->where('selectable', false) as $driver)
                                        <option value="{{ $driver['id'] }}" @selected((string) $selectedDriverId === (string) $driver['id']) disabled>
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

                <!-- Live Dispatch Preview Panel -->
                <div id="dispatch-preview-section" class="mt-6 rounded-xl border border-slate-200 bg-slate-50/50 p-5 select-none animate-fade-in">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#003F87] block mb-3">Live Dispatch Preview</span>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
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

                        <!-- Dispatch Time Read-Only Notice -->
                        <div class="col-span-1 md:col-span-3 pt-2 border-t border-slate-200/60 flex items-center gap-1.5 text-[10.5px] font-semibold text-slate-500">
                            <i class="ti ti-clock-play text-slate-400 text-xs"></i>
                            <span>Dispatch Time: <strong class="text-slate-700">Automatically recorded when dispatch is confirmed.</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Readiness Checklist Panel (Mirrors Backend Rules) -->
                @php
                    $routeOk = !empty($selectedRoute);
                    $busOk = !empty($selectedBusId) && (collect($allBuses)->firstWhere('id', $selectedBusId)['selectable'] ?? false);
                    $driverOk = !empty($selectedDriverId) && (collect($allDrivers)->firstWhere('id', $selectedDriverId)['selectable'] ?? false);
                    $confirmOk = (bool)$confirmDispatch;

                    $allValid = $routeOk && $busOk && $driverOk && $confirmOk;
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

                        <!-- Confirmation Checkbox Check -->
                        <div class="flex items-center gap-2">
                            @if($confirmOk)
                                <i class="ti ti-circle-check text-emerald-500 text-base"></i>
                                <span>Confirmation checked</span>
                            @else
                                <i class="ti ti-circle-x text-rose-400 text-base"></i>
                                <span class="text-slate-450">Confirmation checked</span>
                            @endif
                        </div>

                        <!-- Informational Dispatch Time Notice -->
                        <div class="flex items-center gap-2 col-span-2 text-slate-500 pt-1">
                            <i class="ti ti-info-circle text-blue-500 text-base"></i>
                            <span>Dispatch time will be recorded automatically upon confirmation.</span>
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
                        <input type="checkbox" id="confirm-dispatch" wire:change="$set('confirmDispatch', $event.target.checked)" @checked($confirmDispatch) required tabindex="4"
                            aria-required="true" aria-invalid="{{ $errors->has('confirmDispatch') ? 'true' : 'false' }}"
                            :class="driverSelected && !confirmSelected ? 'border-[#003F87] ring-1 ring-[#003F87]/20 focus:ring-2 focus:ring-[#003F87]/20' : 'border-slate-200'"
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
                            window.switchScreen = async (screenName) => {
                                if (screenName !== 'dispatch' && this.isDirty()) {
                                    if (!(await GoPasigUI.confirm('You have unsaved changes in the Dispatch Builder. Are you sure you want to navigate away?'))) {
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
                            const c = this.$wire.get('confirmDispatch');
                            return (r && r !== '') ||
                                   (b && b !== '') ||
                                   (d && d !== '') ||
                                   c;
                        }
                    };
                };
            }
        });
    </script>
</div>

