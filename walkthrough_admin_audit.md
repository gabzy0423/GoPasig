✅ Module 1 — Walkthrough ng mga Binago
HC-1.1 — overview.blade.php — Active Buses default 12 → 0
Ang <span id="metric-active-buses">12</span> ay pinalitan ng 0. Ang JS na mismo ang mag-o-overwrite ng tamang value pagload ng fleet data. Kung mag-fail ang JS, makikita ng admin ang 0 — hindi yung pekeng 12.

HC-1.2 — overview.blade.php + DashboardController.php — "Pasig Line 1" chip
Sa controller, dinagdag ang:

$primaryRouteName = $routes->first()->name ?? 'Pasig Line 1';

Copy
php
Kinukuha ang pangalan ng unang route mula sa Route::getAllCached(). Ang 'Pasig Line 1' ay nanatili bilang fallback lang — kung walang routes sa DB, may makikita pa rin sa UI. Ipinasa sa view at ginamit sa Blade bilang {{ $primaryRouteName }}.

HC-1.4 — DashboardService.php — Inflated trips fallback
Inalis ang buong if ($tripsCompleted === 0) block. Ngayon kung 0 ang trips ngayon, 0 ang ibinabalik — tama at honest ang data. Hindi na nag-i-inflate ng huge all-time number kapag walang trips pa ngayong araw.

BL-1.1 — DashboardService.php — delayed_buses_yesterday palaging '— today'
Dinagdag ang aktwal na kalkulasyon:

$delayedBusesYesterday = Bus::whereIn('id', $activeBusIdsYesterday)
    ->where('eta', '>=', $delayThreshold)
    ->count();
$delayedDelta = $delayedBuses - $delayedBusesYesterday;

Copy
php
Ngayon ang delta ay nagpapakita ng +2 vs yesterday o -1 vs yesterday — tunay na numero, hindi static string.

BL-1.2 — DashboardService.php — Inconsistent delta column
Dati, "active buses yesterday" ay ginagamit ang started_at + status = 'ongoing' — mali ito kasi walang bus na ongoing kahapon na makikita ngayon. Pinalitan ng:

$activeBusIdsYesterday = Trip::whereDate('ended_at', Carbon::yesterday())
->whereIn('status', ['completed', 'ongoing'])
->pluck('bus_id')->unique()->toArray();

Copy
php
Consistent na ngayon — parehong ginagamit ang ended_at para sa "active" definition sa parehong araw.

BL-1.3 — DashboardController.php + overview.blade.php — "Systems Nominal" laging green
Sa controller, dinagdag ang real health check:

May active ServiceAlert? → critical

May bus sa maintenance? → degraded

Wala? → nominal

Sa Blade, ginawa itong dynamic gamit ang @php match() — ang color ng badge, label text, at dot color ay lahat nagbabago base sa $systemStatus. Kaya ngayon kung may active alert, makikita ng admin ang 🔴 System Critical chip — hindi palaging berde.
