<section id="screen-commuter-trips" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-4 lg:space-y-5">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Commuter Trip Log</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Operations</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Commuter Trip Log</span>
        </div>
    </div>

    <!-- Filters Section -->
    <section class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between bg-white p-4 rounded-xl border border-black/5 shadow-xs">
        <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap">
            <label class="relative block w-full sm:w-[260px]">
                <i class="ti ti-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[14px] text-slate-400"></i>
                <input type="text" id="trips-search-input" placeholder="Search session token..." class="w-full rounded-lg border border-black/10 bg-white py-2 pl-9 pr-3 text-[13px] outline-none focus:border-[#003F87]">
            </label>
            <select id="trips-filter-route" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Routes</option>
                @foreach ($routes as $route)
                    <option value="{{ $route['id'] }}">{{ $route['name'] }}</option>
                @endforeach
            </select>
            <select id="trips-filter-status" class="rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                <option value="all">All Statuses</option>
                <option value="WAITING">Waiting</option>
                <option value="ON_BUS">On Bus</option>
                <option value="ARRIVED">Arrived</option>
                <option value="CANCELLED">Cancelled</option>
            </select>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="fetchCommuterTrips(1)" class="inline-flex items-center gap-1.5 rounded-lg border border-black/10 bg-white px-3 py-2 text-[13px] text-slate-600 hover:bg-slate-50 transition cursor-pointer" title="Reload data">
                <i class="ti ti-refresh text-[14px]"></i>
                <span>Refresh</span>
            </button>
            <button onclick="resetTripsFiltersAction()" class="inline-flex items-center gap-1 text-[13px] text-slate-500 hover:text-slate-700 transition cursor-pointer">
                <i class="ti ti-x text-[14px]"></i>
                <span>Clear filters</span>
            </button>
        </div>
    </section>

    <!-- Data Table -->
    <section class="rounded-2xl border border-black/10 bg-white px-4 py-4 sm:px-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between border-b border-slate-50 pb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Commuter Trips</h2>
                <span id="trips-total-badge" class="rounded-full bg-slate-100 px-2.5 py-1 text-[12px] font-semibold text-slate-500">0 entries</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full table-fixed text-left text-[13px]">
                <thead class="border-b border-black/10 text-[11px] uppercase tracking-wider text-slate-400 bg-slate-50/50">
                    <tr>
                        <th class="w-[28%] py-3 px-3">Session Token</th>
                        <th class="w-[14%] py-3 px-3">Route</th>
                        <th class="w-[17%] py-3 px-3">Origin Stop</th>
                        <th class="w-[17%] py-3 px-3">Destination Stop</th>
                        <th class="w-[10%] py-3 px-3">Status</th>
                        <th class="w-[14%] py-3 px-3">Timestamps</th>
                    </tr>
                </thead>
                <tbody id="trips-table-body" class="divide-y divide-black/6">
                    <tr>
                        <td colspan="6" class="py-12 text-center bg-slate-50/50">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <i class="ti ti-loader animate-spin text-[32px] text-[#003F87]"></i>
                                <p class="text-xs text-slate-400">Loading commuter trips...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div id="trips-pagination" class="mt-4 flex items-center justify-between border-t border-slate-50 pt-4 text-[12px] text-slate-500">
            <!-- Loaded dynamically -->
        </div>
    </section>
</div>
</section>
