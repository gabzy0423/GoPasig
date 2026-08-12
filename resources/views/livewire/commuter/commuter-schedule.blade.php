<div wire:poll.60s
     class="max-w-[768px] mx-auto w-full min-h-screen bg-slate-50 flex flex-col pb-24 relative select-none px-4 pt-4"
>
    <div class="flex flex-col gap-3.5 mb-4 flex-shrink-0">
        <div class="flex flex-col gap-0.5">
            <h2 class="text-[18px] font-medium text-slate-800 leading-tight">Schedule</h2>
            <p class="text-[13px] text-slate-400 font-medium">Official operating windows by route direction</p>
        </div>

        <div class="w-full flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth py-1">
            <button
                wire:click="filterByRoute(null)"
                wire:loading.attr="disabled"
                wire:target="filterByRoute"
                class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1.5
                       {{ is_null($activeRouteFilter) ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]' }}"
            >
                <i wire:loading wire:target="filterByRoute(null)" class="ti ti-loader-2 animate-spin"></i>
                <span>All Routes</span>
            </button>

            @foreach($routes as $route)
                <button
                    wire:click="filterByRoute({{ $route['id'] }})"
                    wire:loading.attr="disabled"
                    wire:target="filterByRoute"
                    class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1.5
                           {{ $activeRouteFilter === $route['id'] ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]' }}"
                >
                    <i wire:loading wire:target="filterByRoute({{ $route['id'] }})" class="ti ti-loader-2 animate-spin"></i>
                    <span>{{ $route['name'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex-grow flex flex-col gap-4 overflow-y-auto no-scrollbar pb-6">
        @forelse($serviceRoutes as $route)
            <section class="flex flex-col gap-3">
                <div class="flex flex-col gap-0.5">
                    <h3 class="text-[15px] font-semibold text-slate-800 leading-tight">{{ $route['name'] }}</h3>
                    @if($route['description'])
                        <p class="text-[12px] text-slate-400 font-medium">{{ $route['description'] }}</p>
                    @endif
                </div>

                <div class="flex flex-col gap-3">
                    @foreach($route['directions'] as $direction)
                        <article class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col gap-3 shadow-2xs">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex flex-col gap-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.05em] text-[#003F87] bg-[#E6F1FB] px-2 py-0.5 rounded-md">
                                            {{ $direction['direction_label'] }}
                                        </span>
                                        <span class="text-[12px] font-semibold {{ $direction['status_label'] === 'Active' ? 'text-[#0F6E56] bg-[#E1F5EE]' : ($direction['status_label'] === 'Inactive' ? 'text-slate-500 bg-slate-100' : 'text-[#854F0B] bg-[#FAEEDA]') }} px-2 py-0.5 rounded-full">
                                            {{ $direction['status_label'] }}
                                        </span>
                                        <span class="text-[12px] font-semibold {{ $direction['is_operating_now'] ? 'text-[#0F6E56] bg-[#E1F5EE]' : (str_starts_with($direction['operating_status_label'], 'Starts in') ? 'text-[#854F0B] bg-[#FAEEDA]' : 'text-slate-500 bg-slate-100') }} px-2 py-0.5 rounded-full">
                                            {{ $direction['operating_status_label'] }}
                                        </span>
                                    </div>
                                    <div class="text-[14px] font-semibold text-slate-800 leading-snug">
                                        {{ $direction['origin'] }} &rarr; {{ $direction['destination'] }}
                                    </div>
                                </div>
                            </div>

                            @if($direction['has_configuration'])
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-slate-50 border border-slate-100 rounded-lg p-3">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.05em] text-slate-400">First Trip</div>
                                        <div class="text-[15px] font-bold text-[#003F87] mt-1">{{ $direction['first_trip_time'] }}</div>
                                    </div>
                                    <div class="bg-slate-50 border border-slate-100 rounded-lg p-3">
                                        <div class="text-[10px] font-bold uppercase tracking-[0.05em] text-slate-400">Last Trip</div>
                                        <div class="text-[15px] font-bold text-[#003F87] mt-1">{{ $direction['last_trip_time'] }}</div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 text-[12.5px] text-slate-600 font-medium">
                                    @if(count($direction['service_windows']) > 1)
                                        <div class="flex flex-col gap-1.5 rounded-lg border border-slate-100 bg-slate-50 p-3">
                                            <div class="text-[10px] font-bold uppercase tracking-[0.05em] text-slate-400">Operating Windows</div>
                                            @foreach($direction['service_windows'] as $index => $window)
                                                <div class="flex items-center justify-between gap-3 text-[12px] font-semibold text-slate-700">
                                                    <span>Window {{ $index + 1 }}</span>
                                                    <span class="text-[#003F87]">{{ $window['first_trip_time'] }} - {{ $window['last_trip_time'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="flex items-start gap-2">
                                        <i class="ti ti-calendar-week text-slate-400 mt-0.5"></i>
                                        <span>Service Days: <strong class="text-slate-700">{{ $direction['service_days_label'] }}</strong></span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <i class="ti ti-settings text-slate-400 mt-0.5"></i>
                                        <span>Service Configuration: <strong class="text-slate-700">{{ $direction['service_configuration_label'] }}</strong></span>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <i class="ti ti-calendar-time text-slate-400 mt-0.5"></i>
                                        <span>Effective Range: <strong class="text-slate-700">{{ $direction['effective_range_label'] }}</strong></span>
                                    </div>
                                    @if($direction['source_label'])
                                        <div class="flex items-start gap-2">
                                            <i class="ti ti-file-check text-slate-400 mt-0.5"></i>
                                            <span>Source: <strong class="text-slate-700">{{ $direction['source_label'] }}</strong></span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="bg-[#FAEEDA] border border-amber-100 rounded-lg p-3 flex items-start gap-2">
                                    <i class="ti ti-calendar-off text-[#854F0B] mt-0.5"></i>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-[13px] font-bold text-[#854F0B]">Official operating hours not configured</span>
                                        <span class="text-[12px] text-[#854F0B]/80 font-medium">This direction is waiting for official public service schedule details.</span>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="w-full py-16 flex flex-col items-center justify-center text-center bg-white border border-slate-200 rounded-xl shadow-2xs">
                <i class="ti ti-calendar-off text-[48px] text-slate-300 mb-3"></i>
                <h3 class="text-[15px] font-semibold text-slate-800">No route service schedules available</h3>
                <p class="text-[13px] text-slate-400 mt-1 max-w-xs">Official operating windows are not available for the selected public route.</p>
            </div>
        @endforelse
    </div>
</div>
