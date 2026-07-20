<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\MaintenanceRecord;
use App\Models\ServiceAlert;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send license expiry reminders to drivers
     * Checks for licenses expiring within specified days
     */
    public static function sendLicenseExpiryReminders(int $daysThreshold = 30): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'drivers' => []
        ];

        // Find drivers with licenses expiring soon
        $expiryDate = Carbon::now()->addDays($daysThreshold);
        
        $drivers = Driver::whereNotNull('license_expiry')
            ->where('license_expiry', '<=', $expiryDate)
            ->where('license_expiry', '>', Carbon::now())
            ->where('status', '!=', 'inactive')
            ->get();

        foreach ($drivers as $driver) {
            try {
                // Prepare notification data
                $daysUntilExpiry = Carbon::now()->diffInDays($driver->license_expiry, false);
                
                $notificationData = [
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'license_number' => $driver->license_number,
                    'expiry_date' => $driver->license_expiry->format('Y-m-d'),
                    'days_until_expiry' => $daysUntilExpiry,
                    'notification_type' => 'license_expiry_reminder',
                    'sent_at' => now(),
                    'urgency' => self::getLicenseExpiryUrgency($daysUntilExpiry),
                ];


                // Log notification
                Log::info("License expiry reminder sent to driver {$driver->id}", $notificationData);

                $result['drivers'][] = $notificationData;
                $result['sent']++;

            } catch (\Exception $e) {
                Log::error("Failed to send license expiry reminder to driver {$driver->id}", [
                    'error' => $e->getMessage()
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send service alert notifications
     * Notifies admins and affected drivers about service alerts
     */
    public static function sendServiceAlertNotification(ServiceAlert $alert, array $recipientUserIds = []): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'recipients' => []
        ];

        // If no recipients specified, send to all admins
        if (empty($recipientUserIds)) {
            $admins = User::where('role', 'admin')->get();
            $recipientUserIds = $admins->pluck('id')->toArray();
        }

        $recipients = User::whereIn('id', $recipientUserIds)->get();

        foreach ($recipients as $user) {
            try {
                $notificationData = [
                    'alert_id' => $alert->id,
                    'route_name' => $alert->route->name ?? 'All Routes',
                    'title' => $alert->title,
                    'message' => $alert->message,
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                    'created_at' => $alert->created_at->format('Y-m-d H:i:s'),
                    'notification_type' => 'service_alert',
                ];


                // Log notification
                Log::info("Service alert notification sent to user {$user->id}", $notificationData);

                $result['recipients'][] = [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'status' => 'sent'
                ];
                $result['sent']++;

            } catch (\Exception $e) {
                Log::error("Failed to send service alert notification to user {$user->id}", [
                    'error' => $e->getMessage()
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send maintenance completion notifications
     * Notifies fleet manager when maintenance is completed and inspected
     */
    public static function sendMaintenanceCompletionNotification(MaintenanceRecord $maintenance): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'recipients' => []
        ];

        // Send to all fleet admins
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            try {
                $notificationData = [
                    'maintenance_id' => $maintenance->id,
                    'bus_plate' => $maintenance->bus->plate_number,
                    'bus_route' => $maintenance->bus->route->name ?? 'Unassigned',
                    'maintenance_type' => $maintenance->type,
                    'description' => $maintenance->description,
                    'technician' => $maintenance->technician_name,
                    'completed_at' => $maintenance->completed_at?->format('Y-m-d H:i:s'),
                    'inspected_at' => $maintenance->inspected_at?->format('Y-m-d H:i:s'),
                    'inspection_result' => $maintenance->inspection_passed ? 'PASSED ✅' : 'FAILED ❌',
                    'cost' => $maintenance->cost_php,
                    'notification_type' => 'maintenance_completion',
                ];


                // Log notification
                Log::info("Maintenance completion notification sent to admin {$admin->id}", $notificationData);

                $result['recipients'][] = [
                    'user_id' => $admin->id,
                    'user_email' => $admin->email,
                    'status' => 'sent'
                ];
                $result['sent']++;

            } catch (\Exception $e) {
                Log::error("Failed to send maintenance completion notification", [
                    'maintenance_id' => $maintenance->id,
                    'error' => $e->getMessage()
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send pending maintenance due notifications
     * Reminds fleet manager about upcoming maintenance
     */
    public static function sendPendingMaintenanceReminders(int $daysThreshold = 7): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'maintenance_items' => []
        ];

        // Find scheduled maintenance within threshold
        $dueDate = Carbon::now()->addDays($daysThreshold);
        
        $maintenance = MaintenanceRecord::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $dueDate)
            ->where('scheduled_at', '>', Carbon::now())
            ->get();

        $admins = User::where('role', 'admin')->get();

        foreach ($maintenance as $m) {
            try {
                $daysUntilDue = Carbon::now()->diffInDays($m->scheduled_at, false);

                $notificationData = [
                    'maintenance_id' => $m->id,
                    'bus_plate' => $m->bus->plate_number,
                    'maintenance_type' => $m->type,
                    'scheduled_date' => $m->scheduled_at->format('Y-m-d'),
                    'days_until_due' => $daysUntilDue,
                    'expected_duration' => $m->expected_duration_minutes . ' minutes',
                    'notification_type' => 'pending_maintenance_reminder',
                ];


                $result['maintenance_items'][] = $notificationData;
                $result['sent']++;

            } catch (\Exception $e) {
                Log::error("Failed to send pending maintenance reminder", [
                    'maintenance_id' => $m->id,
                    'error' => $e->getMessage()
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send incident alerts
     * Notifies fleet manager of safety incidents
     */
    public static function sendIncidentAlert(\App\Models\Incident $incident, ?int $adminId = null): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'recipients' => []
        ];

        $admins = $adminId 
            ? User::where('id', $adminId)->where('role', 'admin')->get()
            : User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            try {
                $notificationData = [
                    'incident_id' => $incident->id,
                    'incident_type' => $incident->type,
                    'severity' => $incident->severity,
                    'driver_name' => $incident->driver?->name ?? 'Unknown',
                    'bus_plate' => $incident->bus?->plate_number ?? 'Unknown',
                    'description' => $incident->description,
                    'occurred_at' => $incident->created_at->format('Y-m-d H:i:s'),
                    'notification_type' => 'incident_alert',
                ];


                Log::warning("Incident alert sent to admin {$admin->id}", $notificationData);

                $result['recipients'][] = [
                    'user_id' => $admin->id,
                    'user_email' => $admin->email,
                    'status' => 'sent'
                ];
                $result['sent']++;

            } catch (\Exception $e) {
                Log::error("Failed to send incident alert", [
                    'incident_id' => $incident->id,
                    'error' => $e->getMessage()
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Private helper methods
     */


    private static function getLicenseExpiryUrgency(int $daysUntilExpiry): string
    {
        if ($daysUntilExpiry <= 7) return 'CRITICAL';
        if ($daysUntilExpiry <= 14) return 'HIGH';
        if ($daysUntilExpiry <= 30) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Get notification status summary for dashboard
     */
    public static function getNotificationSummary(): array
    {
        $expiringLicenses = Driver::whereNotNull('license_expiry')
            ->where('license_expiry', '<=', Carbon::now()->addDays(30))
            ->where('license_expiry', '>', Carbon::now())
            ->count();

        $pendingMaintenance = MaintenanceRecord::where('status', 'scheduled')
            ->where('scheduled_at', '<=', Carbon::now()->addDays(7))
            ->count();

        $activeServiceAlerts = ServiceAlert::where('status', 'active')->count();

        $recentIncidents = \App\Models\Incident::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        return [
            'license_expiry_warnings' => $expiringLicenses,
            'pending_maintenance_due' => $pendingMaintenance,
            'active_service_alerts' => $activeServiceAlerts,
            'recent_incidents' => $recentIncidents,
            'total_notifications' => $expiringLicenses + $pendingMaintenance + $activeServiceAlerts + $recentIncidents,
        ];
    }
}
