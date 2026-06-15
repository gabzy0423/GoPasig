
@php
    $statusClasses = [
        'reported' => 'bg-[#FAEEDA] text-[#854F0B]',
        'under_review' => 'bg-[#E6F1FB] text-[#185FA5]',
        'resolved' => 'bg-[#EAF3DE] text-[#3B6D11]',
    ];
    $statusLabels = [
        'reported' => 'Open',
        'under_review' => 'Under Investigation',
        'resolved' => 'Resolved'
    ];
@endphp

<section id="screen-incidents" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-5 lg:space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Incident Reports</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Operations</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Incident Reports</span>
        </div>
    </div>
    
    <!-- Success/Error Alert Box -->
    <div id="incidents-alert" class="hidden p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-1.5">
            <i class="ti ti-circle-check text-[16px]"></i>
            <span id="incidents-alert-message"></span>
        </div>
        <button onclick="document.getElementById('incidents-alert').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- Page Filters -->
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-end">
        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <div class="flex items-center gap-2 rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-600">
                <i class="ti ti-calendar text-slate-500"></i>
                <input type="date" id="filter-incidents-date-start" value="{{ $dateStart }}" class="bg-transparent outline-none">
                <span class="text-slate-400">to</span>
                <input type="date" id="filter-incidents-date-end" value="{{ $dateEnd }}" class="bg-transparent outline-none">
            </div>
            <select id="filter-incidents-route" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Routes</option>
                @foreach($routes as $route)
                    <option value="{{ $route->name }}" {{ $routeFilter == $route->name ? 'selected' : '' }}>{{ $route->name }}</option>
                @endforeach
            </select>
            <select id="filter-incidents-type" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all" {{ $typeFilter == 'all' ? 'selected' : '' }}>All Types</option>
                <option value="Breakdown" {{ $typeFilter == 'Breakdown' ? 'selected' : '' }}>Breakdown</option>
                <option value="Accident" {{ $typeFilter == 'Accident' ? 'selected' : '' }}>Accident</option>
                <option value="Delay" {{ $typeFilter == 'Delay' ? 'selected' : '' }}>Delay</option>
                <option value="Route Deviation" {{ $typeFilter == 'Route Deviation' ? 'selected' : '' }}>Route Deviation</option>
                <option value="Passenger Disturbance" {{ $typeFilter == 'Passenger Disturbance' ? 'selected' : '' }}>Passenger Disturbance</option>
                <option value="Other" {{ $typeFilter == 'Other' ? 'selected' : '' }}>Other</option>
            </select>
            <select id="filter-incidents-status" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all" {{ $statusFilter == 'all' ? 'selected' : '' }}>All Statuses</option>
                <option value="Open" {{ $statusFilter == 'Open' ? 'selected' : '' }}>Open</option>
                <option value="Under Investigation" {{ $statusFilter == 'Under Investigation' ? 'selected' : '' }}>Under Investigation</option>
                <option value="Resolved" {{ $statusFilter == 'Resolved' ? 'selected' : '' }}>Resolved</option>
            </select>
            <button wire:click="exportIncidentReport" class="inline-flex items-center gap-1.5 rounded-lg border border-black/15 bg-white px-3.5 py-2 text-[14px] font-medium text-[#001F44] hover:bg-slate-50 cursor-pointer transition-colors shadow-sm">
                <i class="ti ti-table-export text-[16px] text-slate-500"></i>
                <span>Export report</span>
            </button>
            <button onclick="openLogIncidentModal()" class="inline-flex items-center gap-1.5 rounded-lg bg-[#003F87] px-3.5 py-2 text-[14px] font-medium text-white hover:bg-[#002D62] cursor-pointer transition-colors shadow-sm">
                <i class="ti ti-plus text-[16px]"></i>
                <span>Log Incident</span>
            </button>
        </div>
    </section>

    <!-- Metrics Cards Grid -->
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-sm transition-shadow">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Total incidents today</span>
                <i class="ti ti-alert-triangle text-[16px] text-[#E24B4A]"></i>
            </div>
            <p id="metric-incidents-today" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $incidentMetrics->total_today }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-sm transition-shadow">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Open incidents</span>
                <i class="ti ti-circle-dot text-[16px] text-[#BA7517]"></i>
            </div>
            <p id="metric-incidents-open" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $incidentMetrics->open }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-sm transition-shadow">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Under investigation</span>
                <i class="ti ti-search text-[16px] text-[#378ADD]"></i>
            </div>
            <p id="metric-incidents-review" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $incidentMetrics->under_investigation }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-sm transition-shadow">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Resolved today</span>
                <i class="ti ti-circle-check text-[16px] text-teal-600"></i>
            </div>
            <p id="metric-incidents-resolved-today" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $incidentMetrics->resolved_today }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-sm transition-shadow">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Avg resolution time</span>
                <i class="ti ti-clock text-[16px] text-slate-500"></i>
            </div>
            <p id="metric-incidents-avg-time" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $incidentMetrics->avg_resolution_minutes }} min</p>
        </article>
    </section>

    <!-- Active Incidents List -->
    <section class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Active incidents</h2>
                <span id="active-incidents-count-badge" class="rounded-full px-2.5 py-1 text-[12px] font-medium {{ count($activeIncidents) > 0 ? 'bg-[#FCEBEB] text-[#A32D2D]' : 'bg-slate-100 text-slate-500' }}">{{ count($activeIncidents) }} active</span>
            </div>
            <div class="inline-flex rounded-full border border-black/10 bg-slate-50 p-1 text-[12px]" data-sort-active="{{ $activeSort }}">
                <button class="rounded-full px-3 py-1 font-medium transition cursor-pointer {{ $activeSort === 'newest' ? 'bg-white text-[#003F87] shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Newest first</button>
                <button class="rounded-full px-3 py-1 font-medium transition cursor-pointer {{ $activeSort === 'priority' ? 'bg-white text-[#003F87] shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Priority first</button>
            </div>
        </div>
        
        <div id="active-incidents-list" class="space-y-2">
            @if (count($activeIncidents) === 0)
                <div class="grid min-h-[220px] place-items-center rounded-xl border border-dashed border-black/10 bg-slate-50/70 p-6 text-center">
                    <div class="space-y-2">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-200/80 text-slate-500">
                            <i class="ti ti-shield-check text-[24px]"></i>
                        </div>
                        <p class="text-[14px] font-medium text-slate-500">No active incidents</p>
                        <p class="text-[12px] text-slate-400">All clear</p>
                    </div>
                </div>
            @else
                @foreach ($activeIncidents as $incident)
                    <article onclick="openIncidentDrawerAction({{ $incident->id }})" class="flex cursor-pointer items-start justify-between gap-3 rounded-xl border border-black/10 bg-white px-4 py-3 transition hover:border-[#003F87]/30 hover:bg-[#F8FBFF] hover:shadow-sm">
                        <div class="mt-1 h-[46px] w-[3px] shrink-0 rounded-full {{ $incident->status === 'reported' ? 'bg-[#BA7517]' : 'bg-[#378ADD]' }}"></div>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <h3 class="truncate text-[14px] font-medium text-[#001F44]">{{ $incident->title }}</h3>
                            <p class="text-[12px] text-slate-500">{{ $incident->incident_id }} • {{ $incident->bus_plate }} • {{ $incident->driver_name }} • {{ $incident->route_name }}</p>
                        </div>
                        <div class="min-w-[150px] space-y-1 text-right">
                            <p class="text-[12px] text-slate-400">Reported {{ $incident->reported_at->timezone('Asia/Manila')->diffForHumans() }}</p>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $statusClasses[$incident->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabels[$incident->status] ?? $incident->status }}</span>
                        </div>
                    </article>
                @endforeach
            @endif
        </div>
    </section>

    <!-- Toggle Resolved Log -->
    <section class="space-y-3">
        <button wire:click="toggleResolvedLog" class="inline-flex items-center gap-1 rounded-lg border border-black/10 bg-white px-3 py-2 text-[14px] font-medium text-[#001F44] hover:bg-slate-50 cursor-pointer transition-colors shadow-sm">
            <i class="ti ti-chevron-down text-[14px]"></i>
            <span>Show resolved incidents</span>
        </button>
        
        <div id="resolved-incidents-container" class="hidden rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-sm transition-all duration-300">
            <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Resolved incidents</h2>
                <span id="resolved-incidents-count-badge" class="rounded-full bg-teal-50 px-2 py-0.5 text-xs text-teal-700 font-semibold border border-teal-200">{{ count($resolvedIncidents) }} resolved</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[900px] w-full table-fixed text-left text-[13px]">
                    <thead class="border-b border-black/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <tr>
                            <th class="py-3 px-3 w-[15%]">Incident ID</th>
                            <th class="py-3 px-3 w-[45%]">Details / Title</th>
                            <th class="py-3 px-3 w-[15%]">Route</th>
                            <th class="py-3 px-3 w-[15%]">Date Resolved</th>
                            <th class="py-3 px-3 w-[10%]">Status</th>
                        </tr>
                    </thead>
                    <tbody id="resolved-incidents-table-body" class="divide-y divide-black/6">
                        @forelse($resolvedIncidents as $incident)
                            <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="openIncidentDrawerAction({{ $incident->id }})">
                                <td class="py-3 px-3 font-mono text-[12px] text-slate-600 font-semibold">{{ $incident->incident_id }}</td>
                                <td class="py-3 px-3">
                                    <div class="font-medium text-[#001F44]">{{ $incident->title }}</div>
                                    <div class="text-[11px] text-slate-400 truncate">{{ $incident->description }}</div>
                                </td>
                                <td class="py-3 px-3 text-slate-600">{{ $incident->route_name }}</td>
                                <td class="py-3 px-3 text-slate-500 font-mono text-[12px]">{{ $incident->updated_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                <td class="py-3 px-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $statusClasses[$incident->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabels[$incident->status] ?? $incident->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">No resolved incidents yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Details Drawer -->
    <div id="details-drawer-container" class="hidden fixed inset-0 z-40 transition-opacity">
        <button onclick="closeIncidentDrawerAction()" class="absolute inset-0 bg-black/30 backdrop-blur-sm transition-opacity"></button>
        <aside class="absolute inset-y-0 right-0 z-50 h-full w-full max-w-[420px] border-l border-black/10 bg-white shadow-2xl flex flex-col justify-between">
            <div>
                <!-- Header -->
                <div class="relative border-b border-black/10 px-5 py-4 bg-slate-50/50">
                    <p id="drawer-incident-id" class="font-mono text-[11px] text-slate-400 uppercase tracking-widest font-semibold"></p>
                    <h3 id="drawer-incident-title" class="mt-1 pr-10 text-[18px] font-bold text-[#001F44]"></h3>
                    <button onclick="closeIncidentDrawerAction()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
                </div>
                
                <!-- Content -->
                <div class="px-5 py-5 space-y-5 overflow-y-auto">
                    <div class="space-y-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Description</span>
                        <p id="drawer-incident-description" class="text-[13px] text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-lg border border-black/5"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Incident Type</span>
                            <span class="mt-1 inline-flex items-center gap-1 text-[13px] font-medium text-slate-800">
                                <i class="ti ti-alert-triangle text-[#E24B4A]"></i>
                                <span id="drawer-incident-type"></span>
                            </span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status</span>
                            <div class="mt-1">
                                <span id="drawer-incident-status" class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide"></span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <h4 class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Associated Trip & Fleet Details</h4>
                        <div class="grid grid-cols-2 gap-3 text-[13px]">
                            <div class="bg-slate-50 p-2.5 rounded border border-black/5">
                                <span class="text-[9px] text-slate-400 block font-semibold uppercase">Bus Assigned</span>
                                <span id="drawer-bus-plate" class="font-bold text-[#001F44]"></span>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded border border-black/5">
                                <span class="text-[9px] text-slate-400 block font-semibold uppercase">Route</span>
                                <span id="drawer-route-name" class="font-semibold text-slate-700 truncate block"></span>
                            </div>
                            <div class="col-span-2 bg-slate-50 p-2.5 rounded border border-black/5">
                                <span class="text-[9px] text-slate-400 block font-semibold uppercase">Driver Name</span>
                                <span id="drawer-driver-name" class="font-bold text-[#001F44]"></span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Reported At</span>
                        <p id="drawer-reported-at" class="text-[12px] text-slate-600 mt-1 font-mono"></p>
                    </div>
                </div>
            </div>

            <!-- Action Footer -->
            <div id="drawer-actions-footer" class="border-t border-black/10 bg-slate-50 px-5 py-4 space-y-2">
            </div>
        </aside>
    </div>

    <!-- Confirm Resolution Modal -->
    <div id="confirm-resolve-modal" class="hidden fixed inset-0 z-[60] grid place-items-center bg-black/40 px-4 transition-all">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl border border-black/10 animate-fade-in-up">
            <h4 class="text-[16px] font-semibold text-[#001F44] flex items-center gap-1.5">
                <i class="ti ti-circle-check text-teal-600 text-lg"></i>
                <span>Confirm resolution</span>
            </h4>
            <p class="mt-2 text-[13px] text-slate-500">Are you sure you want to mark this incident as resolved? If this incident was a <strong>Breakdown</strong>, the associated bus will be restored to <strong>active</strong> status.</p>
            <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                <button onclick="closeResolveIncidentModal()" class="rounded-lg border border-black/10 px-3 py-2 text-[13px] text-slate-600 hover:bg-slate-50 cursor-pointer font-medium">Cancel</button>
                <button onclick="executeResolveIncident()" class="rounded-lg bg-teal-600 px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-teal-700 cursor-pointer shadow-sm">Confirm & Resolve</button>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div id="confirm-delete-modal" class="hidden fixed inset-0 z-[60] grid place-items-center bg-black/40 px-4 transition-all">
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-2xl border border-black/10 animate-fade-in-up">
            <h4 class="text-[16px] font-semibold text-[#A32D2D] flex items-center gap-1.5">
                <i class="ti ti-alert-triangle text-[#A32D2D] text-lg"></i>
                <span>Confirm delete record</span>
            </h4>
            <p class="mt-2 text-[13px] text-slate-500">Are you sure you want to permanently delete this incident record? This action cannot be undone.</p>
            <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                <button onclick="closeDeleteIncidentModal()" class="rounded-lg border border-black/10 px-3 py-2 text-[13px] text-slate-600 hover:bg-slate-50 cursor-pointer font-medium">Cancel</button>
                <button onclick="executeDeleteIncident()" class="rounded-lg bg-red-600 px-3.5 py-2 text-[13px] font-semibold text-white hover:bg-red-700 cursor-pointer shadow-sm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Log Incident Modal -->
    <div id="log-incident-modal" class="hidden fixed inset-0 z-[50] grid place-items-center bg-black/40 px-4 backdrop-blur-xs transition-opacity">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-black/10 animate-fade-in-up">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-[#001F44] flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E6F1FB] text-[#003F87]">
                        <i class="ti ti-alert-circle text-lg"></i>
                    </div>
                    <span>Log New Incident</span>
                </h3>
                <button onclick="closeLogIncidentModal()" class="text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
            </div>

            <!-- Form -->
            <form id="incident-creation-form" class="mt-4 space-y-4">
                <!-- Ongoing Trips -->
                <div class="space-y-1">
                    <label for="newTripId" class="text-xs font-semibold text-slate-700">Affected Ongoing Trip <span class="text-red-500">*</span></label>
                    <select id="newTripId" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                        <option value="">Select an ongoing trip...</option>
                        @foreach ($ongoingTrips as $trip)
                            <option value="{{ $trip->id }}">
                                {{ $trip->bus->plate_number }} • {{ $trip->driver->first_name }} {{ $trip->driver->last_name }} • {{ $trip->route->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Auto Fields Information Card -->
                <div id="form-auto-fields" class="hidden p-3 bg-slate-50 rounded-lg border border-black/5 grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Bus Plate</span>
                        <span id="auto-bus-plate" class="font-semibold text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Route</span>
                        <span id="auto-route-name" class="font-semibold text-slate-800 truncate block"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-[10px] text-slate-400 block font-bold uppercase tracking-wider">Assigned Driver</span>
                        <span id="auto-driver-name" class="font-bold text-[#003F87]"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Type -->
                    <div class="space-y-1">
                        <label for="newType" class="text-xs font-semibold text-slate-700">Incident Type <span class="text-red-500">*</span></label>
                        <select id="newType" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="Breakdown">Breakdown</option>
                            <option value="Accident">Accident</option>
                            <option value="Delay">Delay</option>
                            <option value="Route Deviation">Route Deviation</option>
                            <option value="Passenger Disturbance">Passenger Disturbance</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="space-y-1">
                        <label for="newStatus" class="text-xs font-semibold text-slate-700">Initial Status <span class="text-red-500">*</span></label>
                        <select id="newStatus" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="reported">Open (Reported)</option>
                            <option value="under_review">Under Investigation</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-1">
                    <label for="newDescription" class="text-xs font-semibold text-slate-700">Description / Details <span class="text-red-500">*</span></label>
                    <textarea id="newDescription" rows="4" placeholder="Describe what happened, location, damage, or impact to route schedule..." class="w-full rounded-lg border border-black/10 px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] resize-none"></textarea>
                </div>

                <!-- Submit / Cancel -->
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3 mt-4">
                    <button type="button" onclick="closeLogIncidentModal()" class="rounded-lg border border-black/10 px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 cursor-pointer font-medium transition-colors">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4.5 py-2 text-[13px] font-semibold cursor-pointer shadow-sm transition-colors">Log Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        window.GoPasigIncidentsInitialData = {
            activeIncidents: @json($activeIncidents),
            resolvedIncidents: @json($resolvedIncidents),
            incidentMetrics: @json($incidentMetrics)
        };
    </script>

</section>

