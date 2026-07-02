<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The notification data payload.
     */
    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->data['notification_type'] ?? 'general') {
            'license_expiry_reminder' => $this->buildLicenseSubject(),
            'service_alert'           => $this->buildServiceAlertSubject(),
            'maintenance_completion'  => $this->buildMaintenanceCompletionSubject(),
            'pending_maintenance_reminder' => $this->buildPendingMaintenanceSubject(),
            'incident_alert'          => $this->buildIncidentSubject(),
            default                   => 'GoPasig Fleet Notification',
        };

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: ['data' => $this->data],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    // ---------------------------------------------------------------------------
    // Subject builders
    // ---------------------------------------------------------------------------

    private function buildLicenseSubject(): string
    {
        $urgency  = $this->data['urgency'] ?? 'MEDIUM';
        $days     = $this->data['days_until_expiry'] ?? '?';
        $name     = $this->data['driver_name'] ?? 'Driver';
        $prefix   = $urgency === 'CRITICAL' ? '🚨' : ($urgency === 'HIGH' ? '⚠️' : '📋');

        return "{$prefix} License Expiry Reminder — {$name} ({$days} days remaining)";
    }

    private function buildServiceAlertSubject(): string
    {
        $severity = strtoupper($this->data['severity'] ?? 'INFO');
        $title    = $this->data['title'] ?? 'Service Alert';

        return "🚌 Service Alert [{$severity}] — {$title}";
    }

    private function buildMaintenanceCompletionSubject(): string
    {
        $plate  = $this->data['bus_plate'] ?? 'Bus';
        $result = $this->data['inspection_result'] ?? '';

        return "🔧 Maintenance Complete — {$plate} Inspection {$result}";
    }

    private function buildPendingMaintenanceSubject(): string
    {
        $plate = $this->data['bus_plate'] ?? 'Bus';
        $days  = $this->data['days_until_due'] ?? '?';

        return "🛠️ Upcoming Maintenance Reminder — {$plate} due in {$days} day(s)";
    }

    private function buildIncidentSubject(): string
    {
        $severity = strtoupper($this->data['severity'] ?? 'UNKNOWN');
        $type     = $this->data['incident_type'] ?? 'Incident';

        return "🚨 Incident Alert [{$severity}] — {$type}";
    }
}
