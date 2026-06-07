<section id="screen-utilization" class="hidden" style="display: none;">
<div class="space-y-5">

    <!-- PAGE TITLE ROW -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-[20px] font-medium text-[#001F44]">Fleet utilization</h1>
            <p class="text-[13px] text-slate-500 mt-0.5">Last 30 days · {{ \Carbon\Carbon::now('Asia/Manila')->format('M d, Y') }}</p>
        </div>
    </div>

    <!-- TOP GRID: Chart + Per-bus cards -->
    <div class="grid grid-cols-12 gap-4">

        <!-- LEFT: 30-day utilization chart (55%) -->
        <div class="col-span-7 bg-white border border-black/10 rounded-xl p-5">
            <!-- Custom HTML Legend -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[14px] font-medium text-[#001F44]">Utilization over 30 days</h2>
                <div class="flex items-center gap-4 text-[12px]">
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 h-0.5 rounded-full bg-[#003F87] inline-block"></span>
                        <span class="text-slate-600">Active %</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 border-t-2 border-dashed border-[#BA7517] inline-block"></span>
                        <span class="text-slate-600">Maintenance %</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 border-t-2 border-dotted border-[#D3D1C7] inline-block"></span>
                        <span class="text-slate-600">Idle %</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-5 border-t-2 border-dashed border-[#E24B4A] inline-block"></span>
                        <span class="text-slate-600">Target 80%</span>
                    </div>
                </div>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="utilizationLineChart"></canvas>
            </div>
        </div>

        <!-- RIGHT: Per-bus efficiency cards (45%) -->
        <div class="col-span-5">
            <h2 class="text-[13px] font-medium text-[#001F44] mb-3">Per-bus efficiency — today</h2>
            <div class="grid grid-cols-2 gap-2.5 max-h-[340px] overflow-y-auto pr-1">
                {{-- Dynamic $busCards injected from backend --}}

                @foreach($busCards as $bus)
                <div class="bg-white border border-black/10 rounded-[10px] p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono-custom font-semibold text-[13px] text-[#001F44]">{{ $bus['plate'] }}</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $bus['status_class'] }}">{{ $bus['status'] }}</span>
                    </div>
                    <div class="space-y-0.5 text-[11px] text-slate-600">
                        <div class="flex justify-between">
                            <span>Trips today</span>
                            <span class="font-medium font-mono-custom text-[#001F44]">{{ $bus['trips'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pax today</span>
                            <span class="font-medium font-mono-custom text-[#003F87]">{{ $bus['pax'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Km today</span>
                            <span class="font-medium font-mono-custom text-[#001F44]">{{ $bus['km'] }} km</span>
                        </div>
                    </div>
                    <div class="mt-2.5">
                        <div class="w-full bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden">
                            <div class="h-full rounded-full bg-[#003F87]" style="width: {{ $bus['util'] }}%"></div>
                        </div>
                        <div class="text-right text-[10px] font-medium text-[#003F87] mt-0.5">{{ $bus['util'] }}% utilized</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- BOTTOM: Deployment efficiency table -->
    <div class="bg-white border border-black/10 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-black/10 flex items-center justify-between">
            <h2 class="text-[14px] font-medium text-[#001F44]">Deployment efficiency</h2>
            <!-- Route filter tabs -->
            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-black/5">
                <button onclick="filterUtilTable('all')" id="util-tab-all" class="util-tab px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors bg-white text-[#001F44] border border-black/5 shadow-sm">All</button>
                @foreach($routes as $route)
                <button onclick="filterUtilTable('{{ $route->id }}')" id="util-tab-{{ $route->id }}" class="util-tab px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors text-slate-500 hover:text-[#001F44]">{{ $route->name }}</button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-black/8 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-5 w-[18%]">Bus</th>
                        <th class="py-3 px-4 w-[16%]">Route</th>
                        <th class="py-3 px-4 w-[10%]">Trips</th>
                        <th class="py-3 px-4 w-[14%]">Total pax</th>
                        <th class="py-3 px-4 w-[10%]">Km</th>
                        <th class="py-3 px-4 w-[14%]">Util%</th>
                        <th class="py-3 px-5 w-[18%]">Last active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5" id="util-table-body">
                    @foreach($busCards as $row)
                    @php
                        $utilClass = $row['util'] >= 80 ? 'text-[#3B6D11] font-semibold' : ($row['util'] >= 60 ? 'text-[#003F87] font-semibold' : 'text-slate-400 font-medium');
                        $badgeColors = [
                            '1' => 'bg-[#E6F1FB] text-[#003F87]',
                            '2' => 'bg-[#EAF3DE] text-[#3B6D11]',
                            '3' => 'bg-[#FAEEDA] text-[#854F0B]',
                            '4' => 'bg-[#FCEBEB] text-[#E24B4A]'
                        ];
                        $routeColor = $badgeColors[$row['route']] ?? 'bg-slate-100 text-slate-700';
                    @endphp
                    <tr class="hover:bg-[#F5F8FF] transition-colors util-row" data-route="{{ $row['route'] }}">
                        <td class="py-3 px-5 font-mono-custom font-semibold text-[#001F44]">{{ $row['plate'] }}</td>
                        <td class="py-3 px-4"><span class="rounded px-2 py-0.5 text-[11px] font-medium {{ $routeColor }}">{{ $row['routeLabel'] }}</span></td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ $row['trips'] }}</td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ number_format($row['pax']) }}</td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ $row['km'] }} km</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden" style="max-width:80px">
                                    <div class="h-full rounded-full bg-[#003F87]" style="width:{{ $row['util'] }}%"></div>
                                </div>
                                <span class="{{ $utilClass }} text-[12px] font-mono-custom">{{ $row['util'] }}%</span>
                            </div>
                        </td>
                        <td class="py-3 px-5 font-mono-custom text-slate-500">{{ $row['last'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function initUtilization() {
    const chartData = {!! json_encode($chartData) !!};
    const labels = chartData.map((d, index) => index % 5 === 0 ? d.date : '');
    const active = chartData.map(d => d.active);
    const maint = chartData.map(d => d.maintenance);
    const idle = chartData.map(d => d.idle);

    const ctx = document.getElementById('utilizationLineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Active %',
                    data: active,
                    borderColor: '#003F87',
                    backgroundColor: 'rgba(0,63,135,0.06)',
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.35
                },
                {
                    label: 'Maintenance %',
                    data: maint,
                    borderColor: '#BA7517',
                    borderWidth: 2,
                    borderDash: [6, 3],
                    pointRadius: 0,
                    fill: false,
                    tension: 0.35
                },
                {
                    label: 'Idle %',
                    data: idle,
                    borderColor: '#D3D1C7',
                    borderWidth: 2,
                    borderDash: [2, 4],
                    pointRadius: 0,
                    fill: false,
                    tension: 0.35
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,0.05)', drawTicks: false },
                    ticks: { color: 'rgba(0,0,0,0.45)', font: { family: 'DM Sans', size: 10 }, maxRotation: 0 }
                },
                y: {
                    min: 0, max: 100,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { color: 'rgba(0,0,0,0.45)', font: { family: 'DM Sans', size: 10 }, callback: v => v + '%' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#001F44',
                    titleFont: { family: 'DM Sans', size: 11 },
                    bodyFont: { family: 'DM Sans', size: 11 },
                    cornerRadius: 6,
                    callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}%` }
                },
                annotation: {
                    annotations: {
                        target: {
                            type: 'line',
                            yMin: 80, yMax: 80,
                            borderColor: '#E24B4A',
                            borderWidth: 1.5,
                            borderDash: [6, 4],
                            label: {
                                display: true,
                                content: 'Target 80%',
                                position: 'end',
                                backgroundColor: 'transparent',
                                color: '#E24B4A',
                                font: { family: 'DM Sans', size: 10 }
                            }
                        }
                    }
                }
            }
        }
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUtilization);
} else {
    initUtilization();
}

function filterUtilTable(route) {
    document.querySelectorAll('.util-tab').forEach(btn => {
        btn.className = btn.className.replace('bg-white text-[#001F44] border border-black/5 shadow-sm', '').replace('text-slate-500 hover:text-[#001F44]', '') + ' text-slate-500 hover:text-[#001F44]';
    });
    const activeTab = document.getElementById('util-tab-' + route);
    activeTab.className = activeTab.className.replace('text-slate-500 hover:text-[#001F44]', '') + ' bg-white text-[#001F44] border border-black/5 shadow-sm';

    document.querySelectorAll('.util-row').forEach(row => {
        row.style.display = (route === 'all' || row.dataset.route === route) ? '' : 'none';
    });
}
</script>
</section>

