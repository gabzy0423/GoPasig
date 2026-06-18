

@php
    $statusClasses = [
        'scheduled' => 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/15',
        'in_progress' => 'bg-[#FAEEDA] text-[#854F0B] border-[#BA7517]/15',
        'completed' => 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/15',
        'cancelled' => 'bg-[#FCEBEB] text-[#A32D2D] border-[#A32D2D]/15',
    ];
    $statusLabels = [
        'scheduled' => 'Scheduled',
        'in_progress' => 'In progress',
        'completed' => 'Done',
        'cancelled' => 'Cancelled',
    ];
    
    $busStatusClasses = [
        'active' => 'border-[#EAF3DE] bg-[#EAF3DE] text-[#3B6D11] hover:bg-[#EAF3DE]/80',
        'maintenance' => 'border-[#FAEEDA] bg-[#FAEEDA] text-[#854F0B] hover:bg-[#FAEEDA]/80',
        'inactive' => 'border-[#FCEBEB] bg-[#FCEBEB] text-[#A32D2D] hover:bg-[#FCEBEB]/80',
    ];
    $busStatusLabels = [
        'active' => 'Active',
        'maintenance' => 'Maintenance',
        'inactive' => 'Offline',
    ];
@endphp


<section id="screen-maintenance" class="hidden animate-fade-in" style="display: none;">

    <!-- LIST CONTAINER -->
    <div id="maintenance-list-container" class="space-y-5 lg:space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Maintenance Management</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Operations</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span id="maintenance-breadcrumb-current" class="text-slate-600 font-bold">Maintenance Management</span>
        </div>
    </div>
    <!-- Success Message Alert Container -->
    <div id="maintenance-alert" class="hidden p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-1.5">
            <i class="ti ti-circle-check text-[16px]"></i>
            <span id="maintenance-alert-message"></span>
        </div>
        <button onclick="document.getElementById('maintenance-alert').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- Header Section -->
    <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-end">
        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <button id="btn-export-maintenance" class="inline-flex items-center gap-1.5 rounded-lg border border-black/15 bg-white px-3.5 py-2 text-[14px] font-medium text-[#001F44] hover:bg-slate-50 disabled:opacity-60 cursor-pointer transition">
                <i class="ti ti-table-export text-[16px] text-slate-500"></i>
                <span>Export log</span>
            </button>
            <button id="btn-add-maintenance" class="inline-flex items-center gap-1.5 rounded-lg bg-[#003F87] px-4 py-2 text-[14px] font-medium text-white hover:bg-[#002d62] cursor-pointer transition shadow-sm">
                <i class="ti ti-tool text-[16px]"></i>
                <span>Schedule maintenance</span>
            </button>
        </div>
    </section>

    <!-- Metrics Cards -->
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-xs transition">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Total fleet</span>
                <i class="ti ti-bus text-[16px] text-[#378ADD]"></i>
            </div>
            <p id="summary-total-fleet" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $maintenanceSummary->total_fleet }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-xs transition">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Active units</span>
                <i class="ti ti-circle-check text-[16px] text-teal-600"></i>
            </div>
            <p id="summary-active-units" class="mt-2 text-[24px] font-medium text-[#0F6E56]">{{ $maintenanceSummary->active_units }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-xs transition">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Under maintenance</span>
                <i class="ti ti-tool text-[16px] text-[#BA7517]"></i>
            </div>
            <p id="summary-under-maintenance" class="mt-2 text-[24px] font-medium text-[#854F0B]">{{ $maintenanceSummary->under_maintenance }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-xs transition">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Offline / offline</span>
                <i class="ti ti-alert-triangle text-[16px] text-[#E24B4A]"></i>
            </div>
            <p id="summary-breakdown-count" class="mt-2 text-[24px] font-medium text-[#A32D2D]">{{ $maintenanceSummary->breakdown_count }}</p>
        </article>
        <article class="rounded-xl bg-slate-100/70 p-4 border border-black/5 hover:shadow-xs transition">
            <div class="flex items-center justify-between text-[12px] text-slate-500">
                <span>Due for service</span>
                <i class="ti ti-clock-exclamation text-[16px] text-purple-600"></i>
            </div>
            <p id="summary-due-for-service" class="mt-2 text-[24px] font-medium text-[#001F44]">{{ $maintenanceSummary->due_for_service }}</p>
            <p class="mt-1 text-[11px] text-slate-400 font-semibold">within 7 days</p>
        </article>
    </section>

    <!-- Matrix & Upcoming Section -->
    <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Fleet Health Matrix -->
        <div class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-xs">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-[16px] font-medium text-[#001F44]">Fleet health matrix</h2>
                <span id="bus-health-badge" class="rounded-full bg-slate-100 px-2.5 py-1 text-[12px] font-semibold text-slate-500">{{ count($busHealth) }} units</span>
            </div>
            <div id="bus-health-grid" class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                @forelse($busHealth as $bus)
                    <button onclick="openBusDrawer('{{ $bus->bus_id }}')" class="text-left rounded-xl border px-3 py-3 hover:shadow-xs transition cursor-pointer {{ $busStatusClasses[$bus->status] ?? 'border-slate-200 bg-slate-50' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[13px] font-bold">{{ $bus->bus_id }}</span>
                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $bus->route_color }}"></span>
                        </div>
                        <p class="mt-2 text-[10px] opacity-80 font-medium">{{ $bus->assigned_route ?: 'No Route Assigned' }}</p>
                        <p class="mt-2 text-[11px] font-extrabold uppercase tracking-wider">{{ $busStatusLabels[$bus->status] ?? $bus->status }}</p>
                        @if($bus->status === 'maintenance' && !empty($bus->completion_time))
                            <p class="mt-1 text-[9.5px] font-bold text-[#BA7517]">Est. Done: {{ $bus->completion_time }}</p>
                        @endif
                    </button>
                @empty
                    <p class="col-span-full text-center text-slate-400 py-8">No bus health data.</p>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Schedule -->
        <div class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-xs">
            <div class="mb-4 flex items-center justify-between border-b border-slate-50 pb-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Upcoming schedule</h2>
                <span class="rounded-full bg-[#E6F1FB] px-2.5 py-1 text-[12px] font-semibold text-[#185FA5]">Next 30 days</span>
            </div>
            <div id="upcoming-schedule-wrapper">
                @if($upcomingSchedule->isEmpty())
                    <div class="grid min-h-[220px] place-items-center rounded-xl border border-dashed border-black/10 bg-slate-50/70 p-6 text-center">
                        <div class="space-y-2">
                            <i class="ti ti-calendar-off text-[48px] text-slate-300"></i>
                            <p class="text-[14px] text-slate-500 font-semibold">No scheduled maintenance in the next 30 days</p>
                        </div>
                    </div>
                @else
                    <div class="max-h-[280px] space-y-2 overflow-y-auto pr-1">
                        @foreach($upcomingSchedule as $entry)
                            <div onclick="openBusDrawer('{{ $entry->bus_id }}')" class="rounded-xl border border-black/10 bg-white px-3 py-3 border-l-[3px] border-l-[#185FA5] hover:bg-slate-50 transition cursor-pointer">
                                <p class="text-[13px] font-semibold text-[#001F44]">{{ \Illuminate\Support\Carbon::parse($entry->scheduled_date)->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                                <p class="text-[12px] text-slate-500 mt-0.5">Bus <strong class="text-slate-700 font-mono">{{ $entry->bus_id }}</strong> — {{ $entry->description }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Maintenance Logs -->
    <section class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-xs">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3 border-b border-slate-50 pb-3">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Maintenance log</h2>
                <p class="text-[13px] text-slate-500">All recorded maintenance entries</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="log-type-filter" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                    <option value="all" {{ $logTypeFilter === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="Preventive" {{ $logTypeFilter === 'Preventive' ? 'selected' : '' }}>Preventive</option>
                    <option value="Corrective" {{ $logTypeFilter === 'Corrective' ? 'selected' : '' }}>Corrective</option>
                    <option value="Inspection" {{ $logTypeFilter === 'Inspection' ? 'selected' : '' }}>Inspection</option>
                </select>
                <select id="log-status-filter" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                    <option value="all" {{ $logStatusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="Scheduled" {{ $logStatusFilter === 'Scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="In progress" {{ $logStatusFilter === 'In progress' ? 'selected' : '' }}>In progress</option>
                    <option value="Done" {{ $logStatusFilter === 'Done' ? 'selected' : '' }}>Done</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table id="maintenance-log-table" class="min-w-[980px] w-full table-fixed text-left text-[13px]">
                <thead class="border-b border-black/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-50/50">
                    <tr>
                        <th class="py-3 px-3 w-[15%]">Date</th>
                        <th class="py-3 px-3 w-[12%]">Bus Plate</th>
                        <th class="py-3 px-3 w-[12%]">Type</th>
                        <th class="py-3 px-3 w-[23%]">Description</th>
                        <th class="py-3 px-3 w-[13%]">Technician</th>
                        <th class="py-3 px-3 w-[13%]">Inspector</th>
                        <th class="py-3 px-3 w-[12%]">Status</th>
                        <th class="py-3 px-3 w-[8%]"></th>
                    </tr>
                </thead>
                <tbody id="maintenance-log-tbody" class="divide-y divide-black/6">
                    @forelse($maintenanceLogs as $row)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-3 text-slate-600 font-mono text-[12px]">{{ \Illuminate\Support\Carbon::parse($row->maintenance_date)->timezone('Asia/Manila')->format('M d, Y H:i') }}</td>
                            <td class="py-3 px-3 font-mono text-[#003F87] font-bold">{{ $row->bus_id }}</td>
                            <td class="py-3 px-3"><span class="font-medium text-slate-800">{{ $row->type }}</span></td>
                            <td class="py-3 px-3 text-slate-500 truncate" title="{{ $row->description }}">{{ $row->description }}</td>
                            <td class="py-3 px-3 text-slate-700 font-medium">{{ $row->technician_name ?: '—' }}</td>
                            <td class="py-3 px-3 text-slate-700 font-medium">{{ $row->inspected_by ?: '—' }}</td>
                            <td class="py-3 px-3">
                                <span class="rounded px-2.5 py-0.5 text-[11px] font-bold border uppercase {{ $statusClasses[$row->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabels[$row->status] ?? $row->status }}</span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <button onclick="openDetailDrawer({{ $row->id }})" class="text-[#003F87] hover:text-[#002d62] font-semibold text-[12px] transition cursor-pointer">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center bg-slate-50/50">
                                <i class="ti ti-calendar-off text-[48px] text-slate-300"></i>
                                <p class="text-[16px] font-bold text-slate-500 mt-2">No maintenance logs yet.</p>
                                <p class="text-[13px] text-slate-400 mt-1">Schedule service using the button above.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="maintenance-log-pagination" class="mt-4">
            <!-- Paginated links generated dynamically or server layout -->
            {{ $maintenanceLogs->links() }}
        </div>
    </section>

    <!-- FORM CONTAINER (HIDDEN BY DEFAULT) -->
    <div id="maintenance-form-container" class="hidden animate-fade-in">
        <!-- Form Header -->
        <div class="flex items-center gap-4 mb-6">
            <button type="button" onclick="closeScheduleModal()" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition shadow-sm cursor-pointer">
                <i class="ti ti-arrow-left text-lg"></i>
            </button>
            <div>
                <h1 id="modal-title-text" class="text-xl font-bold text-slate-900">Schedule Maintenance</h1>
                <p id="modal-subtitle-text" class="text-xs text-slate-500 mt-0.5">Fill in the details to schedule or update a bus unit's maintenance record.</p>
            </div>
        </div>

        <!-- Form Card -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-sm">
            <form id="maintenance-schedule-form" class="space-y-6 max-w-4xl" onsubmit="saveMaintenanceSchedule(event)">
                <input type="hidden" id="form-record-id" name="id" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Bus Selection -->
                    <div class="space-y-2">
                        <label for="form-bus-id" class="text-xs font-bold uppercase tracking-wider text-slate-500">Select Bus Unit <span class="text-red-500">*</span></label>
                        <select id="form-bus-id" name="bus_id" required class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
                            <option value="">Select bus plate...</option>
                            @foreach (App\Models\Bus::all() as $b)
                                <option value="{{ $b->id }}">{{ $b->plate_number }} ({{ $b->status }})</option>
                            @endforeach
                        </select>
                        <span id="error-bus-id" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                    </div>

                    <!-- Service Type -->
                    <div class="space-y-2">
                        <label for="form-type" class="text-xs font-bold uppercase tracking-wider text-slate-500">Service Type <span class="text-red-500">*</span></label>
                        <select id="form-type" name="type" required class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
                            <option value="Preventive">Preventive</option>
                            <option value="Corrective">Corrective</option>
                            <option value="Inspection">Inspection</option>
                        </select>
                    </div>

                    <!-- Scheduled Date & Time -->
                    <div class="space-y-2">
                        <label for="form-scheduled-at" class="text-xs font-bold uppercase tracking-wider text-slate-500">Scheduled Date & Time <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="form-scheduled-at" name="scheduled_at" required class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-3 pr-4 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <span id="error-scheduled-at" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                    </div>

                    <!-- Technician Name -->
                    <div class="space-y-2">
                        <label for="form-technician-name" class="text-xs font-bold uppercase tracking-wider text-slate-500">Technician Name</label>
                        <input type="text" id="form-technician-name" name="technician_name" placeholder="e.g. John Mechanic" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <span id="error-technician-name" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                    </div>

                    <!-- Estimated Cost -->
                    <div class="space-y-2">
                        <label for="form-cost" class="text-xs font-bold uppercase tracking-wider text-slate-500">Estimated Cost (PHP)</label>
                        <input type="number" step="0.01" id="form-cost" name="cost_php" placeholder="0.00" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <span id="error-cost" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                    </div>

                    <!-- Service Status -->
                    <div class="space-y-2">
                        <label for="form-status" class="text-xs font-bold uppercase tracking-wider text-slate-500">Service Status <span class="text-red-500">*</span></label>
                        <select id="form-status" name="status" required class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In Progress (Bus will go offline)</option>
                            <option value="completed">Completed (Bus will restore to active)</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Expected Duration -->
                    <div class="space-y-2">
                        <label for="form-expected-duration" class="text-xs font-bold uppercase tracking-wider text-slate-500">Expected Duration (Minutes)</label>
                        <input type="number" min="1" id="form-expected-duration" name="expected_duration_minutes" placeholder="120" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                        <span id="error-expected-duration" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                    </div>
                </div>

                <!-- Work Details / Description -->
                <div class="space-y-2">
                    <label for="form-description" class="text-xs font-bold uppercase tracking-wider text-slate-500">Work Details / Description <span class="text-red-500">*</span></label>
                    <textarea id="form-description" name="description" required rows="4" placeholder="Describe work required (e.g. Engine tune-up, replacing worn brake pads...)" class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 px-3 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white resize-none"></textarea>
                    <span id="error-description" class="hidden text-xs text-red-500 font-medium block mt-1"></span>
                </div>

                <!-- Submit / Cancel -->
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button type="button" onclick="closeScheduleModal()" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 cursor-pointer transition-colors">Cancel</button>
                    <button type="submit" id="maintenance-submit-btn" class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-5 py-2 text-xs font-bold cursor-pointer shadow-sm transition-colors">
                        Schedule Service
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Slide-out Details Drawer (Unified for Bus & Maintenance Record Details) -->
    <div id="maintenance-detail-drawer" class="hidden fixed inset-0 z-[55] transition-opacity">
        <button class="absolute inset-0 bg-black/30 backdrop-blur-xs transition-opacity" onclick="closeDetailDrawer()"></button>
        <aside class="absolute inset-y-0 right-0 z-50 h-full w-full max-w-[380px] border-l border-black/10 bg-white shadow-2xl flex flex-col justify-between transform transition-transform duration-300 translate-x-0">
            <!-- Record detail view -->
            <div id="drawer-record-content" class="hidden flex-1 flex flex-col justify-between min-h-0">
                <div class="overflow-y-auto flex-1">
                    <div class="relative border-b border-black/10 px-5 py-4 bg-slate-50/50">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest font-mono">Service Record Details</p>
                        <h3 id="drawer-record-bus-plate" class="mt-1 pr-10 text-[18px] font-bold text-[#001F44]">Bus Plate</h3>
                        <button onclick="closeDetailDrawer()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
                    </div>
                    <div class="px-5 py-5 space-y-4">
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Description of Work</span>
                            <p id="drawer-record-description" class="text-[13px] text-slate-600 bg-slate-50 p-3 rounded-lg border border-black/5 leading-relaxed">Work description...</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Service Type</span>
                                <span id="drawer-record-type" class="font-medium text-slate-800 text-[13px]">Type</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status</span>
                                <div class="mt-0.5" id="drawer-record-status-container">
                                    <!-- Dynamic badge -->
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-[13px]">
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Technician</span>
                                <span id="drawer-record-technician" class="font-bold text-slate-700">—</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Recorded Cost</span>
                                <span id="drawer-record-cost" class="font-bold text-[#001F44] font-mono">PHP 0.00</span>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 pt-4 text-[13px]">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Scheduled Date</span>
                            <span id="drawer-record-date" class="font-medium text-slate-700 font-mono">Date</span>
                        </div>
                    </div>
                </div>
                <div class="border-t border-black/10 bg-slate-50 px-5 py-4 space-y-2">
                    <button id="btn-action-start" class="hidden w-full rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer shadow-sm">Start Service</button>
                    <button id="btn-action-complete" class="hidden w-full rounded-lg bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer shadow-sm">Complete Service</button>
                    <button id="btn-action-cancel" class="hidden w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Cancel Service</button>
                    <button id="btn-action-edit" class="w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Edit Service Details</button>
                    <button id="btn-action-delete" class="w-full rounded-lg border border-red-200 hover:bg-red-50 text-red-600 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Delete Record</button>
                </div>
            </div>

            <!-- Bus profile view -->
            <div id="drawer-bus-content" class="hidden flex-1 flex flex-col justify-between min-h-0">
                <div class="overflow-y-auto flex-1">
                    <div class="relative border-b border-black/10 px-5 py-4 bg-slate-50/50">
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest font-mono">Bus Unit Profile</p>
                        <h3 id="drawer-bus-plate" class="mt-1 pr-10 text-[18px] font-bold text-[#001F44]">Bus Plate</h3>
                        <button onclick="closeDetailDrawer()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
                    </div>
                    <div class="px-5 py-5 space-y-4 text-[13px]">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Operational Route</span>
                                <span id="drawer-bus-route" class="font-medium text-slate-800">Route</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status</span>
                                <div class="mt-0.5" id="drawer-bus-status-container">
                                    <!-- Dynamic badge -->
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Capacity</span>
                                <span id="drawer-bus-capacity" class="font-bold text-slate-700">0 passengers</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Current Passengers</span>
                                <span id="drawer-bus-passengers" class="font-bold text-[#003F87]">0 aboard</span>
                            </div>
                        </div>
                        <div id="drawer-bus-completion-container" class="hidden border-t border-slate-100 pt-4">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Expected Completion Time</span>
                            <span id="drawer-bus-completion-time" class="font-bold text-[#BA7517]">Time</span>
                        </div>
                        <div class="border-t border-slate-100 pt-4 space-y-2">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Recent Services</span>
                            <div id="drawer-bus-services-list" class="space-y-1.5 max-h-[220px] overflow-y-auto">
                                <!-- Dynamic service list -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="border-t border-black/10 bg-slate-50 px-5 py-4 space-y-2">
                    <button id="btn-bus-schedule" class="w-full rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer shadow-sm">Schedule Maintenance</button>
                    <button onclick="closeDetailDrawer()" class="w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Close Drawer</button>
                </div>
            </div>
        </aside>
    </div>
</div>

    <script>
        window.GoPasigMaintenanceInitialData = {
            summary: @json($maintenanceSummary),
            busHealth: @json($busHealth),
            upcomingSchedule: @json($upcomingSchedule),
            logs: @json($maintenanceLogs->items()),
            currentPage: {{ $maintenanceLogs->currentPage() }},
            lastPage: {{ $maintenanceLogs->lastPage() }}
        };
    </script>

</section>

