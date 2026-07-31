<div class="max-w-[680px] mx-auto w-full min-h-screen bg-slate-50 flex flex-col pb-24 relative select-none px-4 pt-4">
    <!-- SECTION 1 — PAGE HEADER -->
    <div class="flex justify-between items-start mb-4 flex-shrink-0">
        <div class="flex flex-col gap-0.5 pr-4">
            <h2 class="text-[20px] font-medium text-slate-800 leading-tight">Service Alerts</h2>
            <p class="text-[13px] text-slate-400 font-medium">Real-time updates for Libreng Sakay routes</p>
        </div>
        @if($unreadCount > 0)
            <span class="flex-shrink-0 px-2.5 py-0.5 rounded-full bg-[#FCEBEB] text-[#A32D2D] text-[12px] font-bold shadow-2xs leading-normal animate-pulse">
                {{ $unreadCount }} new
            </span>
        @endif
    </div>

    <!-- SECTION 2 — FILTER BAR -->
    <div class="flex flex-col gap-2 mb-4 flex-shrink-0">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth py-1">
            @php
                $pills = [
                    'all' => ['label' => 'All', 'active' => 'bg-[#003F87] text-white border-none shadow-2xs'],
                    'delays' => ['label' => 'Delays', 'active' => 'bg-[#FAEEDA] text-[#854F0B] border-none shadow-2xs'],
                    'route changes' => ['label' => 'Route changes', 'active' => 'bg-[#E6F1FB] text-[#185FA5] border-none shadow-2xs'],
                    'suspensions' => ['label' => 'Suspensions', 'active' => 'bg-[#FCEBEB] text-[#A32D2D] border-none shadow-2xs'],
                    'maintenance' => ['label' => 'Maintenance', 'active' => 'bg-[#F1EFE8] text-[#5F5E5A] border-none shadow-2xs']
                ];
            @endphp

            @foreach($pills as $key => $pill)
                <button
                    wire:click="filterAlerts('{{ $key }}')"
                    wire:loading.attr="disabled"
                    wire:target="filterAlerts"
                    class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1.5
                           {{ $filter === $key ? $pill['active'] : 'bg-transparent text-slate-400 border-[0.5px] border-slate-200 hover:bg-slate-50' }}"
                >
                    <i wire:loading wire:target="filterAlerts('{{ $key }}')" class="ti ti-loader-2 animate-spin"></i>
                    <span>{{ $pill['label'] }}</span>
                </button>
            @endforeach
        </div>
        
        <div class="flex justify-end pr-0.5">
            <span class="text-[12px] text-slate-400 font-medium">
                {{ $alertCount }} active {{ Str::plural('alert', $alertCount) }}
            </span>
        </div>
    </div>

    <!-- SECTION 3 — ACTIVE ALERTS LIST -->
    <div 
        wire:poll.60s
        class="flex-grow flex flex-col gap-[10px] relative"
    >
        <!-- Wire:loading subtle pulse overlay -->
        <div 
            wire:loading 
            class="absolute inset-0 bg-slate-50/50 backdrop-blur-xs z-30 transition-all duration-300 pointer-events-none rounded-xl"
        >
            <div class="w-full h-full flex items-center justify-center">
                <div class="w-8 h-8 rounded-full border-2 border-[#003F87] border-t-transparent animate-spin"></div>
            </div>
        </div>

        @forelse($activeAlerts as $alert)
            @php
                $isExpanded = in_array($alert->id, $expandedAlerts);
                $isLongMessage = strlen($alert->message) > 180;
                
                // Set color theme parameters based on type
                $typeTheme = match ($alert->type) {
                    'delay' => [
                        'border' => 'border-l-[#BA7517]',
                        'bg' => 'bg-[#FAEEDA]',
                        'text' => 'text-[#854F0B]',
                        'label' => 'Delay'
                    ],
                    'route_change' => [
                        'border' => 'border-l-[#378ADD]',
                        'bg' => 'bg-[#E6F1FB]',
                        'text' => 'text-[#185FA5]',
                        'label' => 'Route Change'
                    ],
                    'suspension' => [
                        'border' => 'border-l-[#E24B4A]',
                        'bg' => 'bg-[#FCEBEB]',
                        'text' => 'text-[#A32D2D]',
                        'label' => 'Suspension'
                    ],
                    'maintenance' => [
                        'border' => 'border-l-[#888780]',
                        'bg' => 'bg-[#F1EFE8]',
                        'text' => 'text-[#5F5E5A]',
                        'label' => 'Maintenance'
                    ],
                    default => [
                        'border' => 'border-l-[#003F87]',
                        'bg' => 'bg-[#E6F1FB]',
                        'text' => 'text-[#003F87]',
                        'label' => 'Info'
                    ]
                };
            @endphp
            <div 
                class="bg-white rounded-md p-4 border-[0.5px] border-slate-200 border-l-[4px] flex flex-col gap-[10px] transition-all duration-200 shadow-none
                       {{ $typeTheme['border'] }} {{ $alert->is_read ? 'opacity-85' : '' }}"
            >
                <!-- Row 1: Alert type chip + Timestamp -->
                <div class="flex justify-between items-center text-[12px]">
                    <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md {{ $typeTheme['bg'] }} {{ $typeTheme['text'] }}">
                        {{ $typeTheme['label'] }}
                    </span>
                    
                    <div class="flex items-center gap-1.5 text-slate-400 font-semibold">
                        @if(!$alert->is_read)
                            <!-- Pulsing Red Dot for Unread -->
                            <div class="h-2 w-2 rounded-full bg-[#E24B4A] animate-pulse flex-shrink-0"></div>
                        @endif
                        <span>Posted {{ $alert->posted_at }}</span>
                    </div>
                </div>

                <!-- Row 2: Alert Headline -->
                <h3 class="text-[15px] font-medium text-slate-800 leading-snug">
                    {{ $alert->headline }}
                </h3>

                <!-- Row 3: Alert message body with read more -->
                <div class="text-[13px] text-slate-500 font-medium leading-[1.6]">
                    @if($isLongMessage && !$isExpanded)
                        <span>{{ Str::limit($alert->message, 180) }}</span>
                        <button
                            wire:click="toggleExpand({{ $alert->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleExpand({{ $alert->id }})"
                            class="text-[#003F87] font-bold ml-1 active:underline inline-flex items-center gap-0.5 disabled:opacity-60 disabled:pointer-events-none"
                        >
                            <i wire:loading wire:target="toggleExpand({{ $alert->id }})" class="ti ti-loader-2 animate-spin"></i>
                            <span>Read more</span>
                        </button>
                    @else
                        <span>{{ $alert->message }}</span>
                        @if($isLongMessage && $isExpanded)
                            <button
                                wire:click="toggleExpand({{ $alert->id }})"
                                wire:loading.attr="disabled"
                                wire:target="toggleExpand({{ $alert->id }})"
                                class="text-[#003F87] font-bold ml-1 active:underline inline-flex items-center gap-0.5 disabled:opacity-60 disabled:pointer-events-none"
                            >
                                <i wire:loading wire:target="toggleExpand({{ $alert->id }})" class="ti ti-loader-2 animate-spin"></i>
                                <span>Show less</span>
                            </button>
                        @endif
                    @endif
                </div>

                <!-- Row 4: Affected Routes -->
                @if(count($alert->affected_routes) > 0)
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-[12px] border-t border-slate-50 pt-2.5">
                        <span class="text-slate-400 font-bold">Affected:</span>
                        @foreach($alert->affected_routes as $route)
                            <span class="inline-flex items-center gap-1.5 bg-slate-50 border border-slate-100 rounded-full px-2.5 py-0.5 font-semibold text-slate-600">
                                <span class="h-2 w-2 rounded-full" style="background-color: {{ $route['color'] }}"></span>
                                <span>{{ $route['name'] }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif

                <!-- Row 5: Estimated Resolution -->
                @if($alert->type === 'delay' || $alert->type === 'route_change')
                    <div class="flex items-center gap-1.5 text-[12px] text-slate-400 font-medium leading-none mt-0.5">
                        <i class="ti ti-clock text-[12px]"></i>
                        @if($alert->estimated_resumption)
                            <span>Est. resumption: {{ $alert->estimated_resumption }}</span>
                        @else
                            <span>Estimated resumption: TBA</span>
                        @endif
                    </div>
                @endif

                <!-- Row 6: Mark as Read Button -->
                @if(!$alert->is_read)
                    <div class="flex justify-end border-t border-slate-50 pt-2 mt-1">
                        <button
                            wire:click="markRead({{ $alert->id }})"
                            wire:loading.attr="disabled"
                            wire:target="markRead({{ $alert->id }})"
                            class="text-xs font-bold text-[#003F87]/80 hover:text-[#003F87] active:scale-95 transition-transform disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1"
                        >
                            <i wire:loading wire:target="markRead({{ $alert->id }})" class="ti ti-loader-2 animate-spin"></i>
                            <span wire:loading.remove wire:target="markRead({{ $alert->id }})">Mark as read</span>
                            <span wire:loading wire:target="markRead({{ $alert->id }})">Marking</span>
                        </button>
                    </div>
                @endif

            </div>
        @empty
            <!-- Empty state fallback -->
            <div class="w-full py-16 px-4 flex flex-col items-center justify-center text-center bg-white border border-slate-200 rounded-xl shadow-2xs">
                <div class="w-[60px] h-[60px] rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3.5">
                    <i class="ti ti-bell-off text-[28px]"></i>
                </div>
                <h3 class="text-[14px] text-slate-400 font-medium">No active alerts for this category</h3>
                <button
                    wire:click="filterAlerts('all')"
                    wire:loading.attr="disabled"
                    wire:target="filterAlerts"
                    class="text-[13px] font-bold text-[#003F87] mt-2 active:underline hover:underline disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1.5"
                >
                    <i wire:loading wire:target="filterAlerts('all')" class="ti ti-loader-2 animate-spin"></i>
                    <span>Check back later or view all alerts</span>
                </button>
            </div>
        @endforelse
    </div>

    <!-- SECTION 4 — RESOLVED / ARCHIVED ALERTS -->
    <div class="mt-6 flex flex-col gap-3">
        <!-- Accordion Header -->
        <button
            wire:click="toggleResolved"
            wire:loading.attr="disabled"
            wire:target="toggleResolved"
            class="w-full flex justify-between items-center py-3 px-4 bg-white border border-slate-200 rounded-xl shadow-2xs active:bg-slate-50 transition-colors disabled:opacity-60 disabled:pointer-events-none"
        >
            <span class="text-[14px] font-semibold text-slate-700">Resolved alerts</span>
            
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-slate-400 bg-slate-100 px-2.5 py-0.5 rounded-full leading-normal">
                    {{ $resolvedCount }} resolved
                </span>
                <i wire:loading.remove wire:target="toggleResolved" class="ti ti-chevron-down text-[16px] text-slate-400 transition-transform duration-250 {{ $showResolved ? 'rotate-180' : '' }}"></i>
                <i wire:loading wire:target="toggleResolved" class="ti ti-loader-2 text-[16px] text-slate-400 animate-spin"></i>
            </div>
        </button>

        <!-- Accordion Body (Expanded Resolved Alerts) -->
        @if($showResolved)
            <div 
                class="flex flex-col gap-3 mt-1.5 transition-all duration-300"
                x-transition
            >
                @foreach($resolvedAlerts as $resAlert)
                    <div 
                        class="bg-white rounded-md p-4 border-[0.5px] border-slate-200 border-l-[4px] border-l-[#D1CFC8] flex flex-col gap-[10px] opacity-70 shadow-none"
                    >
                        <!-- Top Row: type label + green resolved status -->
                        <div class="flex justify-between items-center text-[12px]">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-400">
                                {{ $resAlert->type === 'route_change' ? 'Route Change' : ucfirst($resAlert->type) }}
                            </span>
                            <span class="text-[12px] text-emerald-600 font-medium">
                                Resolved at: {{ $resAlert->resolved_at }}
                            </span>
                        </div>

                        <!-- Headline -->
                        <h3 class="text-[14.5px] font-medium text-slate-700 leading-snug">
                            {{ $resAlert->headline }}
                        </h3>

                        <!-- Body -->
                        <p class="text-[12.5px] text-slate-400 font-medium leading-relaxed">
                            {{ $resAlert->message }}
                        </p>

                        <!-- Affected routes -->
                        @if(count($resAlert->affected_routes) > 0)
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-[11.5px] border-t border-slate-50 pt-2">
                                <span class="text-slate-400 font-bold">Affected:</span>
                                @foreach($resAlert->affected_routes as $route)
                                    <span class="inline-flex items-center gap-1.5 bg-slate-50/50 border border-slate-100/50 rounded-full px-2.5 py-0.5 font-semibold text-slate-400">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $route['color'] }}"></span>
                                        <span>{{ $route['name'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
