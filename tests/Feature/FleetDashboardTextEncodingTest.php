<?php

namespace Tests\Feature;

use Tests\TestCase;

class FleetDashboardTextEncodingTest extends TestCase
{
    public function test_fleet_runtime_copy_uses_ascii_safe_fallbacks_and_separators(): void
    {
        $performance = file_get_contents(public_path('js/fleet-dashboard/performance.js'));
        $driverView = file_get_contents(resource_path('views/fleet/performance/drivers/index.blade.php'));
        $incidents = file_get_contents(public_path('js/fleet-dashboard/incidents.js'));
        $incidentView = file_get_contents(resource_path('views/fleet/incidents/index.blade.php'));
        $overview = file_get_contents(public_path('js/fleet-dashboard/overview.js'));
        $overviewView = file_get_contents(resource_path('views/fleet/overview-content.blade.php'));
        $fleetController = file_get_contents(app_path('Http/Controllers/Fleet/FleetController.php'));
        $incidentWorkflow = file_get_contents(app_path('Services/IncidentWorkflowService.php'));

        $this->assertStringContainsString(
            "row.avg_trip_time_minutes + ' min' : 'No data'",
            $performance
        );
        $this->assertStringContainsString(
            "driver.avg_trip_time_minutes + ' min' : 'No data'",
            $performance
        );
        $this->assertStringContainsString(
            "\$row['avg_trip_time_minutes'] . ' min' : 'No data'",
            $driverView
        );
        $this->assertStringContainsString(
            '${escapeIncidentHtml(incident.incident_id)} | ${escapeIncidentHtml(incident.bus_plate)} | ${escapeIncidentHtml(incident.driver_name)} | ${escapeIncidentHtml(incident.route_name)}',
            $incidents
        );
        $this->assertStringContainsString(
            '{{ $incident->incident_id }} | {{ $incident->bus_plate }} | {{ $incident->driver_name }} | {{ $incident->route_name }}',
            $incidentView
        );
        $this->assertStringContainsString('buses on-route | Updated just now', $overview);
        $this->assertStringContainsString("<span>-</span>", $overviewView);
        $this->assertStringContainsString('Reported via %s: %s - %s', $incidentWorkflow);

        $runtimeSources = [
            'performance.js' => preg_replace('/^\s*\/\/.*$/m', '', $performance),
            'incidents.js' => $incidents,
            'incident view' => $incidentView,
            'overview.js' => $overview,
            'overview view' => $overviewView,
            'driver performance view' => $driverView,
            'fleet controller' => $fleetController,
            'incident workflow' => $incidentWorkflow,
        ];

        $mojibakeMarkers = ["\xC3\x83", "\xC3\x82", "\xC3\xA2", "\xEF\xBF\xBD"];

        foreach ($runtimeSources as $label => $source) {
            foreach ($mojibakeMarkers as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $source,
                    "{$label} contains malformed text encoding."
                );
            }
        }
    }
}
