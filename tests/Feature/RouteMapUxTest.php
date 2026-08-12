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

    public function test_admin_live_map_places_official_routes_control_below_its_toolbar(): void
    {
        $view = file_get_contents(resource_path('views/admin/map/index.blade.php'));
        $script = file_get_contents(public_path('js/admin-dashboard/fleet-map.js'));

        $this->assertStringContainsString('id="live-map-toolbar"', $view);
        $this->assertStringContainsString('function alignOfficialRoutesControl()', $script);
        $this->assertStringContainsString("window.matchMedia('(min-width: 1024px)').matches", $script);
        $this->assertStringContainsString('toolbar.offsetTop + toolbar.offsetHeight + LIVE_FLEET_ROUTE_CONTROL_GAP', $script);
        $this->assertStringContainsString("window.addEventListener('resize', alignOfficialRoutesControl)", $script);
    }

    public function test_fleet_monitor_stacks_official_routes_and_map_controls_below_its_toolbar(): void
    {
        $monitor = file_get_contents(resource_path('views/fleet/monitor/index.blade.php'));

        $this->assertStringContainsString('id="fleet-monitor-toolbar"', $monitor);
        $this->assertStringContainsString('id="fleet-monitor-map-controls"', $monitor);
        $this->assertStringContainsString('function alignFleetMonitorOfficialRoutesControl()', $monitor);
        $this->assertStringContainsString('toolbar.offsetTop + toolbar.offsetHeight + MONITOR_ROUTE_CONTROL_GAP', $monitor);
        $this->assertStringContainsString('controlTop + control.offsetHeight + MONITOR_ROUTE_CONTROL_GAP', $monitor);
        $this->assertStringContainsString("window.addEventListener('resize', alignFleetMonitorOfficialRoutesControl)", $monitor);
    }
}
