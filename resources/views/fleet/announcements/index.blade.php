
@php
    $priorityClasses = [
        'High' => 'bg-[#FCEBEB] text-[#A32D2D] border-[#FCEBEB]',
        'Medium' => 'bg-[#E6F1FB] text-[#185FA5] border-[#E6F1FB]',
        'Low' => 'bg-slate-100 text-slate-600 border-slate-100',
    ];
    $statusClasses = [
        'Active' => 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/10',
        'Scheduled' => 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/10',
        'Expired' => 'bg-[#F1EFE8] text-[#5F5E5A] border-[#5F5E5A]/10',
        'Draft' => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
@endphp

<section id="screen-announcements" class="hidden" style="display: none;">
<div class="space-y-4 lg:space-y-5">
    
    <!-- Success/Error Alert Box -->
    <div id="announcements-alert" class="hidden p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-1.5">
            <i class="ti ti-circle-check text-[16px]"></i>
            <span id="announcements-alert-message"></span>
        </div>
        <button onclick="document.getElementById('announcements-alert').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- Page Header -->
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="space-y-1">
            <h1 class="text-[22px] font-medium text-[#001F44]">Announcements</h1>
            <p class="text-[14px] text-slate-500">Libreng Sakay Program - Pasig City</p>
        </div>
        <button onclick="openCreateModalAction()" class="inline-flex items-center gap-1.5 rounded-lg bg-[#003F87] px-4 py-2 text-[14px] font-medium text-white hover:bg-[#002d62] transition shadow-sm cursor-pointer">
            <i class="ti ti-speakerphone text-[16px]"></i>
            <span>New Announcement</span>
        </button>
    </section>

    <!-- Filters Section -->
    <section class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-white p-4 rounded-xl border border-black/5 shadow-xs">
        <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap">
            <label class="relative block w-full sm:w-[240px]">
                <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[14px] text-slate-400"></i>
                <input type="text" id="search-input" placeholder="Search announcements..." class="w-full rounded-lg border border-black/10 bg-white py-2 pl-9 pr-3 text-[13px] outline-none focus:border-[#003F87]">
            </label>
            <select id="filter-priority" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Priorities</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
            <select id="filter-audience" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Audiences</option>
                <option value="Commuters">Commuters</option>
                <option value="Drivers">Drivers</option>
                <option value="Administrators">Administrators</option>
                <option value="All Users">All Users</option>
            </select>
            <select id="filter-status" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Expired">Expired</option>
                <option value="Draft">Draft</option>
            </select>
        </div>
        <button onclick="resetFiltersAction()" class="inline-flex items-center gap-1 text-[13px] text-slate-500 hover:text-slate-700 transition cursor-pointer">
            <i class="ti ti-x text-[14px]"></i>
            <span>Clear filters</span>
        </button>
    </section>

    <!-- Stats Counters -->
    <section class="flex flex-wrap items-center gap-2.5 pb-1">
        <span id="stat-active" class="inline-flex items-center gap-1.5 rounded-full border border-[#3B6D11]/20 bg-[#EAF3DE] px-3.5 py-1.5 text-[13px] font-semibold text-[#3B6D11] shadow-xs">
            <i class="ti ti-circle-check text-[14px]"></i> Active ({{ $announcementStats->active }})
        </span>
        <span id="stat-scheduled" class="inline-flex items-center gap-1.5 rounded-full border border-[#185FA5]/20 bg-[#E6F1FB] px-3.5 py-1.5 text-[13px] font-semibold text-[#185FA5] shadow-xs">
            <i class="ti ti-clock text-[14px]"></i> Scheduled ({{ $announcementStats->scheduled }})
        </span>
        <span id="stat-expired" class="inline-flex items-center gap-1.5 rounded-full border border-[#5F5E5A]/20 bg-[#F1EFE8] px-3.5 py-1.5 text-[13px] font-semibold text-[#5F5E5A] shadow-xs">
            <i class="ti ti-clock-off text-[14px]"></i> Expired ({{ $announcementStats->expired }})
        </span>
        <span id="stat-[#A32D2D]" class="inline-flex items-center gap-1.5 rounded-full border border-[#A32D2D]/20 bg-[#FCEBEB] px-3.5 py-1.5 text-[13px] font-semibold text-[#A32D2D] shadow-xs">
            <i class="ti ti-alert-triangle text-[14px]"></i> High Priority ({{ $announcementStats->high_priority }})
        </span>
    </section>

    <!-- Data Table -->
    <section class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-xs">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-50 pb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">All Announcements</h2>
                <span id="announcements-total-badge" class="rounded-full bg-slate-100 px-2.5 py-1 text-[12px] font-semibold text-slate-500">{{ $announcements->total() }} entries</span>
            </div>
            <div class="inline-flex rounded-full border border-black/10 bg-slate-50 p-1 text-[12px]" data-sort-order="{{ $sortOrder }}">
                <button class="rounded-full px-3 py-1 font-medium transition cursor-pointer {{ $sortOrder === 'newest' ? 'bg-white text-[#003F87] shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Newest first</button>
                <button class="rounded-full px-3 py-1 font-medium transition cursor-pointer {{ $sortOrder === 'oldest' ? 'bg-white text-[#003F87] shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Oldest first</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full table-fixed text-left text-[13px]">
                <thead class="border-b border-black/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-50/50">
                    <tr>
                        <th class="w-[10%] py-3 px-3">Priority</th>
                        <th class="w-[26%] py-3 px-3">Headline</th>
                        <th class="w-[12%] py-3 px-3">Audience</th>
                        <th class="w-[15%] py-3 px-3">Affected Route</th>
                        <th class="w-[13%] py-3 px-3">Posted By</th>
                        <th class="w-[13%] py-3 px-3">Date Posted</th>
                        <th class="w-[10%] py-3 px-3">Status</th>
                        <th class="w-[11%] py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="announcements-table-body" class="divide-y divide-black/6">
                    @forelse($announcements as $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-3">
                                <span class="inline-flex rounded px-2 py-0.5 text-[11px] font-semibold border {{ $priorityClasses[$item->priority] ?? 'bg-slate-100 text-slate-600' }}">{{ $item->priority }}</span>
                            </td>
                            <td class="py-3 px-3 font-semibold text-[#001F44] truncate" title="{{ $item->headline }}">{{ $item->headline }}</td>
                            <td class="py-3 px-3 text-slate-700">{{ $item->audience }}</td>
                            <td class="py-3 px-3 text-slate-600">{{ $item->affected_route ?: 'All Routes' }}</td>
                            <td class="py-3 px-3 text-[12px] text-slate-500 font-medium">{{ $item->posted_by }}</td>
                            <td class="py-3 px-3 text-[12px] text-slate-500 font-mono">{{ $item->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold border {{ $statusClasses[$item->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $item->status }}</span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <div class="inline-flex items-center gap-1 text-slate-500">
                                    <button onclick="viewAnnouncementAction({{ $item->id }})" class="rounded p-1 hover:bg-slate-100 text-slate-600 transition cursor-pointer" title="View Details">
                                        <i class="ti ti-eye text-[16px]"></i>
                                    </button>
                                    <button onclick="openEditModalAction({{ $item->id }})" class="rounded p-1 hover:bg-slate-100 text-[#003F87] transition cursor-pointer" title="Edit">
                                        <i class="ti ti-pencil text-[16px]"></i>
                                    </button>
                                    <button onclick="deleteAnnouncementAction({{ $item->id }})" class="rounded p-1 hover:bg-slate-100 text-[#A32D2D] transition cursor-pointer" title="Delete">
                                        <i class="ti ti-trash text-[16px]"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center bg-slate-50/50">
                                <i class="ti ti-speakerphone text-[48px] text-slate-300"></i>
                                <p class="text-[16px] font-bold text-slate-500 mt-2">No announcements found</p>
                                <p class="text-[13px] text-slate-400 mt-1">Try adjusting your filters or create a new announcement</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div id="announcements-pagination-links" class="mt-4"></div>
    </section>

    <!-- Create / Edit Modal -->
    <div id="announcement-modal" class="hidden fixed inset-0 z-[60] grid place-items-center bg-black/40 px-4 backdrop-blur-xs transition-opacity animate-fade-in">
        <div class="absolute inset-0" onclick="closeModalAction()"></div>
        <div class="relative z-[61] w-full max-w-[540px] rounded-2xl bg-white p-6 shadow-2xl border border-black/10 animate-fade-in-up">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-bold text-[#001F44] flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E6F1FB] text-[#003F87]">
                        <i class="ti ti-speakerphone text-lg"></i>
                    </div>
                    <span id="announcement-modal-title">New Announcement</span>
                </h3>
                <button onclick="closeModalAction()" class="text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
            </div>

            <!-- Form -->
            <form id="announcement-creation-form" class="mt-4 space-y-4">
                <!-- Headline -->
                <div class="space-y-1">
                    <label for="headline" class="text-xs font-semibold text-slate-700">Headline <span class="text-red-500">*</span></label>
                    <input type="text" id="headline" maxlength="100" placeholder="Brief and catchy announcement headline" class="w-full rounded-lg border border-black/10 px-3 py-2 text-[13px] outline-none focus:border-[#003F87]">
                </div>

                <!-- Body -->
                <div class="space-y-1">
                    <label for="body" class="text-xs font-semibold text-slate-700">Message details <span class="text-red-500">*</span></label>
                    <textarea id="body" rows="4" placeholder="Provide full announcement details here..." class="w-full rounded-lg border border-black/10 px-3 py-2 text-[13px] outline-none focus:border-[#003F87] resize-none"></textarea>
                </div>

                <!-- Grid for priority, audience -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label for="priority" class="text-xs font-semibold text-slate-700">Priority <span class="text-red-500">*</span></label>
                        <select id="priority" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="audience" class="text-xs font-semibold text-slate-700">Target Audience <span class="text-red-500">*</span></label>
                        <select id="audience" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="Commuters" selected>Commuters</option>
                            <option value="Drivers">Drivers</option>
                            <option value="Administrators">Administrators</option>
                            <option value="All Users">All Users</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label for="affected_route" class="text-xs font-semibold text-slate-700">Affected Route</label>
                        <select id="affected_route" class="w-full rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-800 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="All Routes">All Routes</option>
                            @foreach ($routes as $route)
                                <option value="{{ $route->name }}">{{ $route->name }} ({{ $route->description }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="expires_at" class="text-xs font-semibold text-slate-700">Expiration Time</label>
                        <input type="datetime-local" id="expires_at" class="w-full rounded-lg border border-black/10 px-3 py-1.5 text-[13px] outline-none focus:border-[#003F87]">
                    </div>
                </div>

                <!-- Scheduled Post -->
                <div class="bg-slate-50 p-3 rounded-lg border border-black/5 space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_scheduled" class="rounded border-black/10 text-[#003F87] focus:ring-[#003F87] cursor-pointer">
                        <label for="is_scheduled" class="text-xs font-semibold text-slate-700 cursor-pointer">Schedule this post for later</label>
                    </div>
                    <div id="schedule-time-container" class="hidden space-y-1 pt-1 animate-fade-in-up">
                        <input type="datetime-local" id="scheduled_at" class="w-full rounded-lg border border-black/10 px-3 py-1.5 text-[13px] outline-none focus:border-[#003F87]">
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3 mt-4">
                    <button type="button" onclick="closeModalAction()" class="rounded-lg border border-black/10 px-4 py-2 text-[13px] text-slate-600 hover:bg-slate-50 cursor-pointer font-medium transition-colors">Cancel</button>

                    <!-- Post as Draft -->
                    <button type="button" id="btn-draft-save" class="rounded-lg border border-[#003F87]/20 hover:bg-[#F8FBFF] text-[#003F87] px-4.5 py-2 text-[13px] font-semibold cursor-pointer transition-colors">Save as Draft</button>

                    <!-- Post Immediately -->
                    <button type="button" id="btn-submit-save" class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4.5 py-2 text-[13px] font-semibold cursor-pointer shadow-sm transition-colors">Post Immediately</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Announcement Details Modal -->
    <div id="view-announcement-modal" class="hidden fixed inset-0 z-[60] grid place-items-center bg-black/40 px-4 backdrop-blur-xs transition-opacity animate-fade-in">
        <div class="absolute inset-0" onclick="closeModalAction()"></div>
        <div class="relative z-[61] w-full max-w-[540px] rounded-2xl bg-white p-6 shadow-2xl border border-black/10 animate-fade-in-up">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span id="view-priority" class="inline-flex rounded px-2.5 py-0.5 text-[11px] font-bold border"></span>
                <button onclick="closeModalAction()" class="text-slate-400 hover:text-slate-600 transition"><i class="ti ti-x text-[20px]"></i></button>
            </div>

            <!-- Content -->
            <div class="mt-4 space-y-4 text-[13px]">
                <h2 id="view-headline" class="text-lg font-bold text-[#001F44]"></h2>

                <p id="view-body" class="text-slate-600 leading-relaxed bg-slate-50 p-4 rounded-xl border border-black/5 whitespace-pre-wrap"></p>

                <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Audience</span>
                        <span id="view-audience" class="font-medium text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Affected Route</span>
                        <span id="view-affected-route" class="font-medium text-slate-800"></span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status</span>
                        <div class="mt-0.5">
                            <span id="view-status" class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold border"></span>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Posted By</span>
                        <span id="view-posted-by" class="font-medium text-slate-800"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4 text-xs font-mono text-slate-500">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block font-sans">Created At</span>
                        <span id="view-created-at"></span>
                    </div>
                    <div id="view-expires-block" class="hidden">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block font-sans">Expires At</span>
                        <span id="view-expires-at"></span>
                    </div>
                    <div id="view-scheduled-block" class="hidden">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block font-sans">Scheduled For</span>
                        <span id="view-scheduled-at"></span>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3 mt-5">
                <button id="view-modal-btn-edit" class="rounded-lg border border-[#003F87]/20 hover:bg-[#F8FBFF] text-[#003F87] px-4.5 py-2 font-semibold cursor-pointer transition-colors text-xs">Edit</button>
                <button onclick="closeModalAction()" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4.5 py-2 font-semibold cursor-pointer transition-colors text-xs">Close</button>
            </div>
        </div>
    </div>
</div>

    <script>
        window.GoPasigAnnouncementsInitialData = {
            announcements: @json($announcements),
            announcementStats: @json($announcementStats)
        };
    </script>

</section>

