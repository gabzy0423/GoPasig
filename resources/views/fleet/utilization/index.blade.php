<section id="screen-utilization" class="hidden animate-fade-in" style="display: none;" data-utilization-endpoint="{{ route('fleet.api.utilization-data') }}">
<div class="space-y-5">

    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Fleet Utilization</h1>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                <span>Dashboard</span>
                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                <span>Fleet</span>
                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                <span class="text-slate-600 font-bold">Fleet Utilization</span>
            </div>
            <p class="text-[11px] font-semibold text-slate-400">Actual operations and deployment activity</p>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-7 bg-white border border-black/10 rounded-xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[14px] font-medium text-[#001F44]">Daily deployment over 30 days</h2>
                <div class="flex items-center gap-1.5 text-[12px]">
                    <span class="w-5 h-0.5 rounded-full bg-[#003F87] inline-block"></span>
                    <span class="text-slate-600">Deployed buses %</span>
                </div>
            </div>
            <div style="height: 280px; position: relative;">
                <canvas id="utilizationLineChart"></canvas>
            </div>
            <p id="utilization-data-status" class="mt-3 text-[10px] font-semibold text-slate-400">Based on actual Trips with a started operation.</p>
        </div>

        <div class="col-span-5">
            <h2 class="text-[13px] font-medium text-[#001F44] mb-3">Actual bus operations - today</h2>
            <div id="util-bus-cards" class="grid grid-cols-2 gap-2.5 max-h-[340px] overflow-y-auto pr-1">
                @forelse($busCards as $bus)
                <div class="bg-white border border-black/10 rounded-[10px] p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-mono-custom font-semibold text-[13px] text-[#001F44]">{{ $bus['plate'] }}</span>
                        <span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide {{ $bus['status_class'] }}">{{ $bus['status'] }}</span>
                    </div>
                    <div class="text-[10px] font-semibold text-slate-400 mb-2">{{ $bus['routeLabel'] }}</div>
                    <div class="space-y-0.5 text-[11px] text-slate-600">
                        <div class="flex justify-between"><span>Trips run today</span><span class="font-medium font-mono-custom text-[#001F44]">{{ $bus['trips'] }}</span></div>
                        <div class="flex justify-between"><span>Recorded boarded</span><span class="font-medium font-mono-custom text-[#003F87]">{{ $bus['boarded'] }}</span></div>
                        <div class="flex justify-between"><span>Peak load</span><span class="font-medium font-mono-custom text-[#001F44]">{{ $bus['peak_load'] }} / {{ $bus['capacity'] }}</span></div>
                        <div class="flex justify-between"><span>Distance</span><span class="font-medium text-slate-400">Deferred</span></div>
                    </div>
                    <div class="mt-2.5">
                        <div class="w-full bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden"><div class="h-full rounded-full bg-[#003F87]" style="width: {{ $bus['util'] }}%"></div></div>
                        <div class="text-right text-[10px] font-medium text-[#003F87] mt-0.5">{{ $bus['util'] }}% peak load</div>
                    </div>
                </div>
                @empty
                <div class="col-span-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-5 text-center text-xs font-semibold text-slate-400">No buses registered.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white border border-black/10 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-black/10 flex items-center justify-between gap-3">
            <h2 class="text-[14px] font-medium text-[#001F44]">Actual deployment activity</h2>
            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-black/5">
                <button onclick="filterUtilTable('all')" id="util-tab-all" class="util-tab px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors bg-white text-[#001F44] border border-black/5 shadow-sm">All</button>
                @foreach($routes as $route)
                <button onclick="filterUtilTable('{{ $route['id'] }}')" id="util-tab-{{ $route['id'] }}" class="util-tab px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors text-slate-500 hover:text-[#001F44]">{{ $route['name'] }}</button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-black/8 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-5 w-[15%]">Bus</th>
                        <th class="py-3 px-4 w-[14%]">Route</th>
                        <th class="py-3 px-4 w-[9%]">Trips</th>
                        <th class="py-3 px-4 w-[14%]">Recorded boarded</th>
                        <th class="py-3 px-4 w-[12%]">Peak load</th>
                        <th class="py-3 px-4 w-[10%]">Distance</th>
                        <th class="py-3 px-4 w-[14%]">Peak load %</th>
                        <th class="py-3 px-5 w-[12%]">Last trip activity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5" id="util-table-body">
                    @forelse($busCards as $row)
                    @php $utilClass = $row['util'] > 0 ? 'text-[#003F87] font-semibold' : 'text-slate-400 font-medium'; @endphp
                    <tr class="hover:bg-[#F5F8FF] transition-colors util-row" data-route="{{ $row['route'] ?? 'standby' }}">
                        <td class="py-3 px-5 font-mono-custom font-semibold text-[#001F44]">{{ $row['plate'] }}</td>
                        <td class="py-3 px-4"><span class="rounded px-2 py-0.5 text-[11px] font-medium bg-slate-100 text-slate-700">{{ $row['routeLabel'] }}</span></td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ $row['trips'] }}</td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ number_format($row['boarded']) }}</td>
                        <td class="py-3 px-4 font-mono-custom text-slate-700">{{ $row['peak_load'] }} / {{ $row['capacity'] }}</td>
                        <td class="py-3 px-4 text-slate-400">Deferred</td>
                        <td class="py-3 px-4"><div class="flex items-center gap-2"><div class="flex-1 bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden" style="max-width:80px"><div class="h-full rounded-full bg-[#003F87]" style="width:{{ $row['util'] }}%"></div></div><span class="{{ $utilClass }} text-[12px] font-mono-custom">{{ $row['util'] }}%</span></div></td>
                        <td class="py-3 px-5 font-mono-custom text-slate-500">{{ $row['last'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="py-8 text-center text-xs font-semibold text-slate-400">No buses registered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    const root = document.getElementById('screen-utilization');
    if (!root) return;

    let chart = null;
    let activeRoute = 'all';
    const endpoint = root.dataset.utilizationEndpoint;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[character]));
    const statusClass = (status) => ({
        Operating: 'bg-[#E6F1FB] text-[#0C447C]',
        Ready: 'bg-[#EAF3DE] text-[#3B6D11]',
        Standby: 'bg-slate-100 text-slate-700',
        Maintenance: 'bg-[#FCEBEB] text-[#A32D2D]',
        Breakdown: 'bg-[#FAEEDA] text-[#854F0B]',
        Inactive: 'bg-slate-100 text-slate-500'
    }[status] || 'bg-slate-100 text-slate-700');

    function renderCards(cards) {
        const container = document.getElementById('util-bus-cards');
        if (!container) return;
        if (!cards.length) {
            container.innerHTML = '<div class="col-span-2 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-5 text-center text-xs font-semibold text-slate-400">No buses registered.</div>';
            return;
        }
        container.innerHTML = cards.map((bus) => `
            <div class="bg-white border border-black/10 rounded-[10px] p-3">
                <div class="flex items-center justify-between mb-2"><span class="font-mono-custom font-semibold text-[13px] text-[#001F44]">${escapeHtml(bus.plate)}</span><span class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide ${statusClass(bus.status)}">${escapeHtml(bus.status)}</span></div>
                <div class="text-[10px] font-semibold text-slate-400 mb-2">${escapeHtml(bus.routeLabel)}</div>
                <div class="space-y-0.5 text-[11px] text-slate-600">
                    <div class="flex justify-between"><span>Trips run today</span><span class="font-medium font-mono-custom text-[#001F44]">${bus.trips}</span></div>
                    <div class="flex justify-between"><span>Recorded boarded</span><span class="font-medium font-mono-custom text-[#003F87]">${bus.boarded}</span></div>
                    <div class="flex justify-between"><span>Peak load</span><span class="font-medium font-mono-custom text-[#001F44]">${bus.peak_load} / ${bus.capacity}</span></div>
                    <div class="flex justify-between"><span>Distance</span><span class="font-medium text-slate-400">Deferred</span></div>
                </div>
                <div class="mt-2.5"><div class="w-full bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden"><div class="h-full rounded-full bg-[#003F87]" style="width:${bus.util}%"></div></div><div class="text-right text-[10px] font-medium text-[#003F87] mt-0.5">${bus.util}% peak load</div></div>
            </div>`).join('');
    }

    function renderTable(cards) {
        const body = document.getElementById('util-table-body');
        if (!body) return;
        if (!cards.length) {
            body.innerHTML = '<tr><td colspan="8" class="py-8 text-center text-xs font-semibold text-slate-400">No buses registered.</td></tr>';
            return;
        }
        body.innerHTML = cards.map((row) => `
            <tr class="hover:bg-[#F5F8FF] transition-colors util-row" data-route="${escapeHtml(row.route ?? 'standby')}">
                <td class="py-3 px-5 font-mono-custom font-semibold text-[#001F44]">${escapeHtml(row.plate)}</td>
                <td class="py-3 px-4"><span class="rounded px-2 py-0.5 text-[11px] font-medium bg-slate-100 text-slate-700">${escapeHtml(row.routeLabel)}</span></td>
                <td class="py-3 px-4 font-mono-custom text-slate-700">${row.trips}</td>
                <td class="py-3 px-4 font-mono-custom text-slate-700">${Number(row.boarded || 0).toLocaleString()}</td>
                <td class="py-3 px-4 font-mono-custom text-slate-700">${row.peak_load} / ${row.capacity}</td>
                <td class="py-3 px-4 text-slate-400">Deferred</td>
                <td class="py-3 px-4"><div class="flex items-center gap-2"><div class="flex-1 bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden" style="max-width:80px"><div class="h-full rounded-full bg-[#003F87]" style="width:${row.util}%"></div></div><span class="${row.util > 0 ? 'text-[#003F87] font-semibold' : 'text-slate-400 font-medium'} text-[12px] font-mono-custom">${row.util}%</span></div></td>
                <td class="py-3 px-5 font-mono-custom text-slate-500">${escapeHtml(row.last)}</td>
            </tr>`).join('');
        filterUtilTable(activeRoute);
    }

    function renderChart(data) {
        if (!window.Chart) return;
        const canvas = document.getElementById('utilizationLineChart');
        if (!canvas) return;
        if (chart) chart.destroy();
        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.map((item, index) => index % 5 === 0 ? item.date : ''),
                datasets: [{
                    label: 'Deployed buses %',
                    data: data.map((item) => item.deployed_percent),
                    borderColor: '#003F87',
                    backgroundColor: 'rgba(0,63,135,0.06)',
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: 'rgba(0,0,0,0.05)', drawTicks: false }, ticks: { color: 'rgba(0,0,0,0.45)', font: { family: 'DM Sans', size: 10 }, maxRotation: 0 } },
                    y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: 'rgba(0,0,0,0.45)', font: { family: 'DM Sans', size: 10 }, callback: (value) => value + '%' } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#001F44', titleFont: { family: 'DM Sans', size: 11 }, bodyFont: { family: 'DM Sans', size: 11 }, cornerRadius: 6, callbacks: { label: (context) => ` Deployed buses: ${context.parsed.y}%` } }
                }
            }
        });
    }

    function renderSnapshot(payload) {
        renderCards(payload.busCards || []);
        renderTable(payload.busCards || []);
        renderChart(payload.chartData || []);
        const status = document.getElementById('utilization-data-status');
        if (status && payload.generatedAt) {
            status.textContent = `Updated ${new Date(payload.generatedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}. Based on actual Trips with a started operation.`;
        }
    }

    async function fetchUtilizationData() {
        const response = await fetch(endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (!payload.success) throw new Error('Invalid utilization response');
        renderSnapshot(payload);
    }

    window.filterUtilTable = function (route) {
        activeRoute = route;
        document.querySelectorAll('.util-tab').forEach((button) => {
            button.classList.remove('bg-white', 'text-[#001F44]', 'border', 'border-black/5', 'shadow-sm');
            button.classList.add('text-slate-500', 'hover:text-[#001F44]');
        });
        const activeTab = document.getElementById('util-tab-' + route) || document.getElementById('util-tab-all');
        if (activeTab) {
            activeTab.classList.remove('text-slate-500', 'hover:text-[#001F44]');
            activeTab.classList.add('bg-white', 'text-[#001F44]', 'border', 'border-black/5', 'shadow-sm');
        }
        document.querySelectorAll('.util-row').forEach((row) => {
            row.style.display = route === 'all' || row.dataset.route === route ? '' : 'none';
        });
    };

    renderSnapshot({ busCards: @json($busCards), chartData: @json($chartData), generatedAt: @json($generatedAt) });
    if (window.GoPasigFleetModules?.registerPoller) {
        window.GoPasigFleetModules.registerPoller('utilization', 'utilization-data', fetchUtilizationData, 15000);
    } else {
        window.setInterval(() => {
            if (!document.hidden && !root.classList.contains('hidden')) fetchUtilizationData().catch(() => {});
        }, 15000);
    }
})();
</script>
</section>
