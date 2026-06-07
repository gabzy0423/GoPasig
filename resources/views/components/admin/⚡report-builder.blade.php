<?php

use Livewire\Component;

new class extends Component
{
    public $reportType = 'ridership';
    public $dateRange = 'Today';
    public $selectedRoutes = ['Route A', 'Route B', 'Route C'];
    public $includeSections = [
        'Total passengers by route',
        'Passengers per trip breakdown',
        'Hourly ridership chart',
        'Boarding demand by stop',
        'Dispatch prediction table'
    ];
    public $format = 'PDF';
    public $notes = '';

    public $history = [];
    public $generating = false;

    public function mount()
    {
        // Version guard: flush any old session that still holds the Dec hardcoded entries.
        // Bump this version string whenever the history schema changes.
        if (session('generated_reports_version') !== 'v2') {
            session()->forget('generated_reports');
            session(['generated_reports_version' => 'v2']);
        }

        // Start with an empty history — no fake seeded entries.
        // History is populated only when the admin actually generates a report.
        if (!session()->has('generated_reports')) {
            session(['generated_reports' => []]);
        }
        $this->history = session('generated_reports', []);
    }

    public function generateReport()
    {
        $this->generating = true;
        
        // Sleep for 1.5 seconds to simulate generation delay
        usleep(1500000);

        $typeLabel = match($this->reportType) {
            'ridership' => 'Ridership Summary',
            'fleet' => 'Fleet Utilization',
            'route' => 'Route Performance',
            'driver' => 'Driver Performance',
            'dispatch' => 'Dispatch Analysis',
            'maintenance' => 'Maintenance Log',
            default => 'System Report',
        };

        $bgClass = match($this->reportType) {
            'ridership' => 'bg-[#E6F1FB] text-[#003F87]',
            'fleet' => 'bg-[#E8F4E0] text-[#639922]',
            'route' => 'bg-[#FEF7ED] text-[#BA7517]',
            'driver' => 'bg-[#FDF2F2] text-[#E24B4A]',
            default => 'bg-slate-100 text-slate-500',
        };

        $timestamp = now()->format('h:i A');
        $dateStr = now()->format('M d');
        $size = $this->format === 'PDF' ? '234 KB' : '48 KB';

        array_unshift($this->history, [
            'title' => $typeLabel . ' · ' . $dateStr,
            'format' => $this->format,
            'size' => $size,
            'date' => $dateStr . ', ' . $timestamp,
            'icon' => 'ti ti-file-text',
            'bg' => $bgClass
        ]);

        session(['generated_reports' => $this->history]);

        $this->generating = false;

        $this->dispatch('reportGenerated', ['title' => $typeLabel . ' · ' . $dateStr, 'format' => $this->format]);
    }

    public function deleteHistory($index)
    {
        if (isset($this->history[$index])) {
            unset($this->history[$index]);
            $this->history = array_values($this->history);
            session(['generated_reports' => $this->history]);
        }
    }

    public function toggleRoute($route)
    {
        if (in_array($route, $this->selectedRoutes)) {
            $this->selectedRoutes = array_values(array_filter($this->selectedRoutes, fn($r) => $r !== $route));
        } else {
            $this->selectedRoutes[] = $route;
        }
    }
};
?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
    <!-- 5A. REPORT BUILDER (Left Column 45% width) -->
    <div class="lg:col-span-4 rounded-xl border border-slate-200 bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[520px]">
        <span class="text-xs font-extrabold uppercase tracking-widest text-[#003F87] border-b border-slate-100 pb-3 flex items-center gap-1.5 shrink-0">
            <i class="ti ti-file-text text-base"></i>
            Generate Report
        </span>

        <form wire:submit.prevent="generateReport" class="mt-4 space-y-4 flex-1 flex flex-col overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-slate-200">
            <!-- 1. Report Type Grid 2x3 -->
            <div class="space-y-1">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">1. Select Report Type</label>
                <div class="grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-600">
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'ridership' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="ridership" class="hidden">
                        <i class="ti ti-users text-sm shrink-0 {{ $reportType === 'ridership' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Ridership Summary
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'fleet' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="fleet" class="hidden">
                        <i class="ti ti-bus text-sm shrink-0 {{ $reportType === 'fleet' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Fleet Utilization
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'route' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="route" class="hidden">
                        <i class="ti ti-route text-sm shrink-0 {{ $reportType === 'route' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Route Performance
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'driver' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="driver" class="hidden">
                        <i class="ti ti-id text-sm shrink-0 {{ $reportType === 'driver' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Driver Performance
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'dispatch' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="dispatch" class="hidden">
                        <i class="ti ti-chart-bar text-sm shrink-0 {{ $reportType === 'dispatch' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Dispatch Analysis
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'maintenance' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" wire:model.live="reportType" value="maintenance" class="hidden">
                        <i class="ti ti-tool text-sm shrink-0 {{ $reportType === 'maintenance' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Maintenance Log
                    </label>
                </div>
            </div>

            <!-- 2. Date Range Segmented Controls -->
            <div class="space-y-1">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">2. Select Date Range</label>
                <div class="flex rounded-lg bg-slate-100 p-1 text-[11px] font-bold text-slate-600">
                    <button type="button" wire:click="$set('dateRange', 'Today')" class="flex-1 py-1 transition rounded-md {{ $dateRange === 'Today' ? 'bg-white text-[#003F87] shadow-sm' : 'hover:text-slate-800' }} cursor-pointer">Today</button>
                    <button type="button" wire:click="$set('dateRange', 'This Week')" class="flex-1 py-1 transition rounded-md {{ $dateRange === 'This Week' ? 'bg-white text-[#003F87] shadow-sm' : 'hover:text-slate-800' }} cursor-pointer">This Week</button>
                    <button type="button" wire:click="$set('dateRange', 'This Month')" class="flex-1 py-1 transition rounded-md {{ $dateRange === 'This Month' ? 'bg-white text-[#003F87] shadow-sm' : 'hover:text-slate-800' }} cursor-pointer">This Month</button>
                    <button type="button" wire:click="$set('dateRange', 'Custom')" class="flex-1 py-1 transition rounded-md {{ $dateRange === 'Custom' ? 'bg-white text-[#003F87] shadow-sm' : 'hover:text-slate-800' }} cursor-pointer">Custom</button>
                </div>
            </div>

            <!-- 3. Route Multi-Select Pills -->
            <div class="space-y-1.5">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">3. Select Route Lines</label>
                <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                    <button type="button" wire:click="toggleRoute('Route A')" class="rounded-full px-3.5 py-1 border transition select-none cursor-pointer {{ in_array('Route A', $selectedRoutes) ? 'bg-[#003F87] text-white border-[#003F87]' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50' }}">
                        Route A
                    </button>
                    <button type="button" wire:click="toggleRoute('Route B')" class="rounded-full px-3.5 py-1 border transition select-none cursor-pointer {{ in_array('Route B', $selectedRoutes) ? 'bg-[#003F87] text-white border-[#003F87]' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50' }}">
                        Route B
                    </button>
                    <button type="button" wire:click="toggleRoute('Route C')" class="rounded-full px-3.5 py-1 border transition select-none cursor-pointer {{ in_array('Route C', $selectedRoutes) ? 'bg-[#003F87] text-white border-[#003F87]' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50' }}">
                        Route C
                    </button>
                </div>
            </div>

            <!-- 4. Include sections checkmarks -->
            <div class="space-y-2">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">4. Include Sections</label>
                <div class="space-y-1.5 text-xs font-semibold text-slate-600">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="includeSections" value="Total passengers by route" class="h-4 w-4 rounded border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        Total passengers by route
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="includeSections" value="Passengers per trip breakdown" class="h-4 w-4 rounded border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        Passengers per trip breakdown
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="includeSections" value="Hourly ridership chart" class="h-4 w-4 rounded border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        Hourly ridership chart
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="includeSections" value="Boarding demand by stop" class="h-4 w-4 rounded border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        Boarding demand by stop
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" wire:model="includeSections" value="Dispatch prediction table" class="h-4 w-4 rounded border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        Dispatch prediction table
                    </label>
                </div>
            </div>

            <!-- 5. Formats (PDF/CSV) -->
            <div class="space-y-1.5">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">5. Choose Format</label>
                <div class="flex gap-4 text-xs font-semibold text-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="radio" wire:model="format" value="PDF" class="h-4 w-4 border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        PDF Report
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="radio" wire:model="format" value="CSV" class="h-4 w-4 border-slate-200 text-[#003F87] focus:ring-[#003F87]/20">
                        CSV Spreadsheet
                    </label>
                </div>
            </div>

            <!-- 6. Notes -->
            <div class="space-y-1 shrink-0">
                <label for="report-notes" class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">6. Additional Notes (Optional)</label>
                <textarea wire:model="notes" id="report-notes" rows="2" placeholder="Add custom footnotes or header titles..." 
                          class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white"></textarea>
            </div>

            <!-- Submit PDF/CSV triggers -->
            <div class="pt-3 shrink-0 border-t border-slate-100">
                <button type="submit" id="generate-report-btn" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#003F87] py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer" {{ $generating ? 'disabled' : '' }}>
                    @if($generating)
                        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Generating...</span>
                    @else
                        <i class="ti ti-download text-sm"></i>
                        <span>Generate Report</span>
                    @endif
                </button>
                <p class="text-center text-[10px] font-bold text-slate-400 mt-1.5 uppercase tracking-wider">Estimated processing: ~1.5 seconds</p>
            </div>
        </form>
    </div>

    <!-- 5B. REPORT PREVIEW + HISTORY (Right Column 55% width) -->
    <div class="lg:col-span-6 space-y-6 flex flex-col h-[520px]">
        <!-- PDF Document Preview Panel -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[280px] shrink-0">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2 block shrink-0">Document Live Preview</span>
            
            <div class="flex-1 overflow-y-auto mt-3 border border-slate-100 rounded-xl bg-slate-50/50 p-4 font-sans leading-normal relative">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 mb-3 shrink-0">
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase" id="preview-report-title">
                            {{ match($reportType) {
                                'ridership' => 'Ridership Summary',
                                'fleet' => 'Fleet Utilization',
                                'route' => 'Route Performance',
                                'driver' => 'Driver Performance',
                                'dispatch' => 'Dispatch Analysis',
                                'maintenance' => 'Maintenance Log',
                                default => 'System Report',
                            } }} · {{ $dateRange }}
                        </h3>
                        <p class="text-[9px] font-bold text-slate-400 mt-0.5 uppercase tracking-wider">Lungsod ng Pasig - Libreng Sakay Program</p>
                    </div>
                    <div class="h-8 w-8 bg-slate-200 rounded flex items-center justify-center text-[8px] font-black text-slate-400 uppercase select-none">Pasig</div>
                </div>
                @php
                    // Real DB stats for the document preview panel
                    $previewTotalPax   = \App\Models\Schedule::sum('passengers');
                    $previewTrips      = \App\Models\Trip::where('status', 'completed')
                                            ->whereDate('ended_at', \Carbon\Carbon::today())
                                            ->count();
                    if ($previewTrips === 0) {
                        $previewTrips = \App\Models\Trip::where('status', 'completed')->count();
                    }
                    $totalBuses        = \App\Models\Bus::count();
                    $activeBuses       = \App\Models\Bus::where('status', 'active')->count();
                    $previewUtil       = $totalBuses > 0 ? round(($activeBuses / $totalBuses) * 100) : 0;
                    $totalSchedules    = \App\Models\Schedule::count();
                    $onTimeCount       = \App\Models\Schedule::where('status', 'like', '%On time%')->count();
                    $previewOnTime     = $totalSchedules > 0 ? round(($onTimeCount / $totalSchedules) * 100) : 100;
                @endphp
                <div class="grid grid-cols-4 gap-2 text-center text-[10px] font-bold text-slate-500 shrink-0 mb-3 border-b border-slate-100 pb-2.5">
                    <div>Total Pax: <strong class="text-slate-800 block text-xs font-black mt-0.5">{{ number_format($previewTotalPax) }}</strong></div>
                    <div>Trips: <strong class="text-slate-800 block text-xs font-black mt-0.5">{{ $previewTrips }} completed</strong></div>
                    <div>Util Rate: <strong class="text-[#003F87] block text-xs font-black mt-0.5">{{ $previewUtil }}%</strong></div>
                    <div>On-Time: <strong class="text-[#639922] block text-xs font-black mt-0.5">{{ $previewOnTime }}%</strong></div>
                </div>
                
                @if($notes)
                    <div class="text-[10px] italic text-slate-500 bg-slate-100 p-2 rounded mb-3 border-l-2 border-[#003F87]">
                        Notes: {{ $notes }}
                    </div>
                @endif

                <!-- Thumbnail visual decoration -->
                <div class="h-16 w-full flex items-end gap-1 px-4 select-none shrink-0 opacity-40">
                    <div class="h-6 w-full bg-[#003F87]"></div>
                    <div class="h-10 w-full bg-[#003F87]"></div>
                    <div class="h-16 w-full bg-[#003F87]"></div>
                    <div class="h-12 w-full bg-[#003F87]"></div>
                    <div class="h-8 w-full bg-[#003F87]"></div>
                    <div class="h-14 w-full bg-[#003F87]"></div>
                    <div class="h-11 w-full bg-[#003F87]"></div>
                </div>
                
                <!-- PDF/CSV Quick Downloads inside preview -->
                <div class="absolute bottom-4 right-4 flex gap-2 shrink-0">
                    <button type="button" onclick="alert('Downloading PDF...')" class="rounded bg-[#003F87] px-3 py-1 text-[10px] font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">Download PDF</button>
                    <button type="button" onclick="alert('Downloading CSV...')" class="rounded border border-slate-200 bg-white px-3 py-1 text-[10px] font-extrabold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Download CSV</button>
                </div>
            </div>
        </div>

        <!-- Generated Reports Log History -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col min-h-0">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2 block shrink-0">Recent reports (last 30 days)</span>
            
            <div class="flex-1 overflow-y-auto mt-2 space-y-2 scrollbar-thin scrollbar-thumb-slate-200" id="reports-history-list">
                @forelse($history as $index => $item)
                    <div class="flex items-center justify-between border-b border-slate-50 pb-2 hover:bg-slate-50/50 p-2.5 rounded-lg transition duration-200" wire:key="history-{{ $index }}">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $item['bg'] }}"><i class="{{ $item['icon'] }} text-base"></i></span>
                            <div class="leading-none">
                                <h4 class="text-xs font-black text-slate-900">{{ $item['title'] }}</h4>
                                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">{{ $item['format'] }} · {{ $item['size'] }} · {{ $item['date'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="alert('Downloading Report...')" class="p-1 text-slate-400 hover:text-[#003F87] cursor-pointer" title="Download"><i class="ti ti-download text-sm"></i></button>
                            <button type="button" wire:click="deleteHistory({{ $index }})" class="p-1 text-slate-400 hover:text-[#E24B4A] cursor-pointer" title="Delete"><i class="ti ti-trash text-sm"></i></button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-400 font-semibold uppercase tracking-wider">No reports generated yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>