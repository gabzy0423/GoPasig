<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteMapUxTest extends TestCase
{
    public function test_route_map_ux_is_loaded_by_leaflet_map_layouts(): void
    {
        $helper = file_get_contents(public_path('js/route-map-ux.js'));
        $this->assertStringContainsString('Schematic visualization based on official stop coordinates.', $helper);
        $this->assertStringContainsString('data-direction="outbound"', $helper);
        $this->assertStringContainsString('setDirectionVisibility', $helper);
        $this->assertStringContainsString('options.showControl !== false', $helper);
        $this->assertStringContainsString("dashArray: direction.includes('in') ? '8 7' : null", $helper);
        $this->assertStringContainsString('Geofence radius:', $helper);
        $this->assertStringContainsString('state.stopGeofences', $helper);
        $this->assertStringContainsString('L.circle([stop.lat, stop.lng]', $helper);

        $admin = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $fleet = file_get_contents(resource_path('views/layouts/fleet.blade.php'));
        $monitor = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));
        $commuter = file_get_contents(resource_path('views/commuter/tracker/index.blade.php'));

        $this->assertStringContainsString("asset('js/route-map-ux.js')", $admin);
        $this->assertStringContainsString("asset('js/route-map-ux.js')", $fleet);
        $this->assertStringContainsString('GoPasigRouteMapUX.mount', $monitor);
        $this->assertStringContainsString('GoPasigRouteMapUX.setRouteFilter(map, route)', $monitor);
        $this->assertStringNotContainsString('routesMap', $monitor);
        $this->assertStringNotContainsString('stopMarkers', $monitor);
        $this->assertStringNotContainsString('Legacy route/stop fallback', $monitor);
        $this->assertStringContainsString("'variant_geometries' => \$mapGeometry['variant_geometries']", $commuter);
    }

    public function test_admin_live_map_uses_header_route_and_direction_filters_without_duplicate_control(): void
    {
        $view = file_get_contents(resource_path('views/admin/map/index.blade.php'));
        $script = file_get_contents(public_path('js/admin-dashboard/fleet-map.js'));
        $dataScript = file_get_contents(public_path('js/admin-dashboard/dashboard-data.js'));

        $this->assertStringContainsString('id="live-map-toolbar"', $view);
        $this->assertStringContainsString('id="live-map-tracked-count"', $view);
        $this->assertStringContainsString('id="live-map-route-filters"', $view);
        $this->assertStringContainsString('id="live-map-direction-outbound"', $view);
        $this->assertStringContainsString('id="live-map-direction-inbound"', $view);
        $this->assertStringContainsString('OUT solid', $view);
        $this->assertStringContainsString('IN dashed', $view);
        $this->assertStringContainsString('lg:left-4 lg:right-4 lg:top-4 lg:m-0 2xl:right-[408px]', $view);
        $this->assertStringContainsString('flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center', $view);
        $this->assertStringContainsString('map-chip-strip scrollbar-none flex min-w-0 flex-1 items-center gap-1.5', $view);
        $this->assertStringNotContainsString('id="universal-search"', $view);
        $this->assertStringContainsString('showControl: false', $script);
        $this->assertStringContainsString('GoPasigRouteMapUX.setDirectionVisibility(liveMap, direction, visible)', $script);
        $this->assertStringContainsString('function refreshLiveFleetMapSize()', $script);
        $this->assertStringContainsString("document.getElementById('live-map-tracked-count')", $script);
        $this->assertStringContainsString('function isLiveMapTrackableBus(bus)', $script);
        $this->assertStringContainsString('const trackedCount = fleetData.filter(bus => isLiveMapTrackableBus(bus) && matchesLiveMapBusFilters(bus)).length;', $script);
        $this->assertStringContainsString("busStatus: String(bus.bus_status ?? bus.status ?? 'unknown').toLowerCase()", $dataScript);
        $this->assertStringContainsString("const standbyCount = fleetData.filter(bus => bus.busStatus === 'inactive').length;", $script);
        $this->assertStringContainsString('`${trackedCount} buses tracked`', $script);
        $this->assertStringNotContainsString('`${totalCount} buses tracked`', $script);
        $this->assertStringContainsString("document.getElementById('live-map-route-filters')", $script);
        $this->assertStringContainsString("routeFilterStrip.querySelectorAll('[data-route-filter]')", $script);
        $this->assertStringContainsString("typeof renderRouteFilterChips === 'function'", $dataScript);
        $this->assertStringContainsString("typeof refreshLiveFleetMapSize === 'function'", $dataScript);
        $this->assertStringNotContainsString('alignOfficialRoutesControl', $script);
        $this->assertStringNotContainsString('LIVE_FLEET_ROUTE_CONTROL_GAP', $script);
    }

    public function test_fleet_monitor_uses_header_route_and_direction_filters_without_duplicate_control(): void
    {
        $monitor = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));

        $this->assertStringContainsString('id="fleet-monitor-toolbar"', $monitor);
        $this->assertStringContainsString('id="fleet-monitor-map-controls"', $monitor);
        $this->assertStringContainsString('id="monitor-direction-outbound"', $monitor);
        $this->assertStringContainsString('id="monitor-direction-inbound"', $monitor);
        $this->assertStringContainsString('OUT solid', $monitor);
        $this->assertStringContainsString('IN dashed', $monitor);
        $this->assertStringContainsString('showControl: false', $monitor);
        $this->assertStringContainsString('GoPasigRouteMapUX.setDirectionVisibility(map, direction, visible)', $monitor);
        $this->assertStringNotContainsString('alignFleetMonitorOfficialRoutesControl', $monitor);
        $this->assertStringNotContainsString('MONITOR_ROUTE_CONTROL_GAP', $monitor);
    }
}
