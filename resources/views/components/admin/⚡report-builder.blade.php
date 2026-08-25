<?php

use App\Models\Route;
use Livewire\Component;

new class extends Component
{
    public $reportType = 'ridership';
    public $routeNames = [];
    public $selectedRoutes = [];
    public $includeSections = [
        'Total passengers by route',
        'Passengers per trip breakdown',
        'Hourly ridership chart',
        'Boarding demand by stop',
        'Dispatch prediction table'
    ];
    public $format = 'CSV';
    public $notes = '';

    public $history = [];
    public $generating = false;

    public function mount()
    {
        $this->routeNames = Route::publicCommuterActiveService()->orderBy('name')->pluck('name')->toArray();
        $this->selectedRoutes = $this->routeNames;

        // Version guard: flush any old session that still holds the Dec hardcoded entries.
        // Bump this version string whenever the history schema changes.
        if (session('generated_reports_version') !== 'v3') {
            session()->forget('generated_reports');
            session(['generated_reports_version' => 'v3']);
        }

        // Start with an empty history — no fake seeded entries.
        // History is populated only when the admin actually generates a report.
        if (!session()->has('generated_reports')) {
            session(['generated_reports' => []]);
        }
        $this->history = session('generated_reports', []);
    }

    public function generateReport(array $payload = [])
    {
        $this->generating = true;

        $typeLabel = $this->reportTypeLabel($payload['reportType'] ?? $this->reportType);
        $periodLabel = trim((string) ($payload['periodLabel'] ?? 'Current period'));
        $hasData = (bool) ($payload['hasData'] ?? false);
        $date = now('Asia/Manila');

        array_unshift($this->history, [
            'title' => $typeLabel . ' · ' . $periodLabel,
            'format' => $this->format,
            'size' => $hasData ? 'Preview saved' : 'No data',
            'date' => $date->format('M d, h:i A'),
            'icon' => 'ti ti-file-text',
            'bg' => $this->reportTypeClass($payload['reportType'] ?? $this->reportType),
        ]);

        $this->history = array_slice($this->history, 0, 30);
        session(['generated_reports' => $this->history]);

        $this->generating = false;

        $this->dispatch('reportGenerated', title: $typeLabel . ' · ' . $periodLabel, format: $this->format);
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

    private function reportTypeLabel(string $reportType): string
    {
        return match($reportType) {
            'ridership' => 'Ridership Summary',
            'fleet' => 'Fleet Utilization',
            'route' => 'Route Performance',
            'driver' => 'Driver Performance',
            'dispatch' => 'Dispatch Analysis',
            'maintenance' => 'Maintenance Log',
            default => 'System Report',
        };
    }

    private function reportTypeClass(string $reportType): string
    {
        return match($reportType) {
            'ridership' => 'bg-[#E6F1FB] text-[#003F87]',
            'fleet' => 'bg-[#E8F4E0] text-[#639922]',
            'route' => 'bg-[#FEF7ED] text-[#BA7517]',
            'driver' => 'bg-[#FDF2F2] text-[#E24B4A]',
            'dispatch' => 'bg-[#E6F1FB] text-[#003F87]',
            'maintenance' => 'bg-slate-100 text-slate-500',
            default => 'bg-slate-100 text-slate-500',
        };
    }
};
?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
    <!-- 5A. REPORT BUILDER (Left Column 45% width) -->
    <div class="lg:col-span-4 rounded-xl border border-slate-200 bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[520px]">
        <span class="text-xs font-extrabold uppercase tracking-widest text-[#003F87] border-b border-slate-100 pb-3 flex items-center gap-1.5 shrink-0">
            <i class="ti ti-file-text text-base"></i>
            Report Builder
        </span>

        <form id="report-builder-form" class="mt-4 space-y-4 flex-1 flex flex-col overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-slate-200">
            <!-- 1. Report Type Grid 2x3 -->
            <div class="space-y-1">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">1. Select Report Type</label>
                <div class="grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-600">
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'ridership' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="ridership" class="hidden">
                        <i class="ti ti-users text-sm shrink-0 {{ $reportType === 'ridership' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Ridership Summary
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'fleet' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="fleet" class="hidden">
                        <i class="ti ti-bus text-sm shrink-0 {{ $reportType === 'fleet' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Fleet Utilization
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'route' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="route" class="hidden">
                        <i class="ti ti-route text-sm shrink-0 {{ $reportType === 'route' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Route Performance
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'driver' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="driver" class="hidden">
                        <i class="ti ti-id text-sm shrink-0 {{ $reportType === 'driver' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Driver Performance
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'dispatch' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="dispatch" class="hidden">
                        <i class="ti ti-chart-bar text-sm shrink-0 {{ $reportType === 'dispatch' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Dispatch Analysis
                    </label>
                    <label class="border p-2.5 flex items-center gap-2 cursor-pointer transition select-none rounded-lg {{ $reportType === 'maintenance' ? 'border-2 border-[#003F87] bg-[#E6F1FB] text-[#0C447C]' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="report_type" wire:model.live="reportType" value="maintenance" class="hidden">
                        <i class="ti ti-tool text-sm shrink-0 {{ $reportType === 'maintenance' ? 'text-[#0C447C]' : 'text-slate-400' }}"></i>
                        Maintenance Log
                    </label>
                </div>
            </div>

            <!-- 2. Reporting period context -->
            <div wire:ignore class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">2. Reporting Period</label>
                <div class="mt-1 flex items-center justify-between gap-3 text-xs font-bold text-slate-600">
                    <span>Uses the shared analytics filter</span>
                    <span class="analytics-period-summary rounded-full bg-white px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-[#003F87] shadow-sm">Today</span>
                </div>
            </div>

            <!-- 3. Route Multi-Select Pills -->
            <div class="space-y-1.5">
                <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">3. Select Route Lines</label>
                <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                    @if(count($routeNames) > 0)
                        @foreach($routeNames as $routeName)
                            <button type="button" wire:click="toggleRoute('{{ $routeName }}')" class="rounded-full px-3.5 py-1 border transition select-none cursor-pointer {{ in_array($routeName, $selectedRoutes) ? 'bg-[#003F87] text-white border-[#003F87]' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50' }}">
                                {{ $routeName }}
                            </button>
                        @endforeach
                    @else
                        <div class="text-xs text-slate-500">No routes available. Add routes first to build route reports.</div>
                    @endif
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

            <!-- 5. Notes -->
            <div class="space-y-1 shrink-0">
                <label for="report-notes" class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">5. Additional Notes (Optional)</label>
                <textarea wire:model="notes" id="report-notes" rows="2" placeholder="Add custom footnotes or header titles..." 
                          class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white"></textarea>
            </div>

            <!-- Submit PDF/CSV triggers -->
            <div class="pt-3 shrink-0 border-t border-slate-100">
                <button type="button" id="generate-report-btn" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#003F87] py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer" {{ $generating ? 'disabled' : '' }}>
                    <i class="ti ti-file-plus text-sm"></i>
                    <span id="report-btn-text">{{ $generating ? 'Saving...' : 'Generate Report Record' }}</span>
                </button>
                <p class="text-center text-[10px] font-bold text-slate-400 mt-1.5 uppercase tracking-wider">Saves the current preview to export history</p>
            </div>
        </form>
    </div>

    <!-- 5B. REPORT PREVIEW + HISTORY (Right Column 55% width) -->
    <div class="lg:col-span-6 space-y-6 flex flex-col h-[520px]">
        <!-- PDF Document Preview Panel -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[280px] shrink-0">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2 block shrink-0">Document Live Preview</span>
            
            <div wire:ignore class="flex-1 overflow-y-auto mt-3 border border-slate-100 rounded-xl bg-slate-50/50 p-4 font-sans leading-normal relative">
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
                            } }} · Current period
                        </h3>
                        <p class="text-[9px] font-bold text-slate-400 mt-0.5 uppercase tracking-wider">
                            Lungsod ng Pasig - Libreng Sakay Program · <span id="preview-period-label">Today</span>
                        </p>
                    </div>
                    <div class="h-8 w-8 bg-slate-200 rounded flex items-center justify-center text-[8px] font-black text-slate-400 uppercase select-none">Pasig</div>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center text-[10px] font-bold text-slate-500 shrink-0 mb-3 border-b border-slate-100 pb-2.5">
                    <div><span id="preview-metric-a-label">Recorded Boarded</span>: <strong class="text-slate-800 block text-xs font-black mt-0.5" id="preview-metric-a">0</strong></div>
                    <div><span id="preview-metric-b-label">Trips</span>: <strong class="text-slate-800 block text-xs font-black mt-0.5" id="preview-metric-b">0 completed</strong></div>
                    <div><span id="preview-metric-c-label">Util Rate</span>: <strong class="text-[#003F87] block text-xs font-black mt-0.5" id="preview-metric-c">0%</strong></div>
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
                
                <div class="absolute bottom-4 right-4 flex gap-2 shrink-0">
                    <button type="button" data-preview-export-button data-preview-export-format="CSV" disabled class="rounded bg-slate-100 px-3 py-1 text-[10px] font-extrabold text-slate-400 cursor-not-allowed">No data to export</button>
                </div>
            </div>
        </div>

        <!-- Export History -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col min-h-0">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-2 block shrink-0">Recent report records (last 30)</span>
            
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
                            <span class="rounded bg-slate-100 px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Record only</span>
                            <button type="button" wire:click="deleteHistory({{ $index }})" class="p-1 text-slate-400 hover:text-[#E24B4A] cursor-pointer" title="Delete"><i class="ti ti-trash text-sm"></i></button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-400 font-semibold uppercase tracking-wider">
                        No report records generated yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
