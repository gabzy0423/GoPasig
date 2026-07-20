<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>GoPasig Notification</title>
    <style>
        /* ─── Reset ─────────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f4f8;
            color: #2d3748;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Wrapper ────────────────────────────────────────────────── */
        .wrapper {
            max-width: 620px;
            margin: 40px auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            background: #ffffff;
        }

        /* ─── Header ─────────────────────────────────────────────────── */
        .header {
            background: linear-gradient(135deg, #1a3c6e 0%, #2563eb 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header-logo {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 1px;
        }
        .header-logo span { color: #93c5fd; }
        .header-subtitle {
            margin-top: 6px;
            font-size: 13px;
            color: #bfdbfe;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* ─── Badge / type pill ─────────────────────────────────────── */
        .badge-wrap { text-align: center; margin-top: -18px; }
        .badge {
            display: inline-block;
            padding: 6px 20px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            border: 2px solid #fff;
        }
        .badge-license  { background: #d97706; color: #fff; }
        .badge-service  { background: #dc2626; color: #fff; }
        .badge-maint    { background: #059669; color: #fff; }
        .badge-pending  { background: #7c3aed; color: #fff; }
        .badge-incident { background: #b91c1c; color: #fff; }
        .badge-default  { background: #2563eb; color: #fff; }

        /* ─── Body ───────────────────────────────────────────────────── */
        .body { padding: 32px 40px; }

        /* ─── Alert box ──────────────────────────────────────────────── */
        .alert-box {
            border-left: 4px solid #2563eb;
            background: #eff6ff;
            border-radius: 0 8px 8px 0;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .alert-box.critical { border-color: #dc2626; background: #fef2f2; }
        .alert-box.high     { border-color: #d97706; background: #fffbeb; }
        .alert-box p { font-size: 14px; line-height: 1.6; }

        /* ─── Data table ─────────────────────────────────────────────── */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
        .info-table tr { border-bottom: 1px solid #e2e8f0; }
        .info-table tr:last-child { border-bottom: none; }
        .info-table td {
            padding: 10px 4px;
            font-size: 14px;
            vertical-align: top;
        }
        .info-table td.label {
            color: #64748b;
            font-weight: 600;
            width: 42%;
            padding-right: 12px;
        }
        .info-table td.value { color: #1e293b; font-weight: 500; }

        /* ─── CTA Button ─────────────────────────────────────────────── */
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #1a3c6e 0%, #2563eb 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* ─── Divider ────────────────────────────────────────────────── */
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }

        /* ─── Footer ─────────────────────────────────────────────────── */
        .footer {
            background: #f8fafc;
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p { font-size: 12px; color: #94a3b8; line-height: 1.7; }
        .footer a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">

    {{-- ── Header ────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-logo">Go<span>Pasig</span></div>
        <div class="header-subtitle">Fleet Management System</div>
    </div>

    {{-- ── Badge ──────────────────────────────────────────────────────── --}}
    <div class="badge-wrap">
        @php
            $type = $data['notification_type'] ?? 'general';
            [$badgeClass, $badgeLabel] = match ($type) {
                'license_expiry_reminder'      => ['badge-license',  '🪪 License Expiry Reminder'],
                'service_alert'                => ['badge-service',  '🚨 Service Alert'],
                'maintenance_completion'       => ['badge-maint',    '✅ Maintenance Complete'],
                'pending_maintenance_reminder' => ['badge-pending',  '🛠️ Maintenance Due'],
                'incident_alert'               => ['badge-incident', '⚠️ Incident Alert'],
                default                        => ['badge-default',  '📢 Notification'],
            };
        @endphp
        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
    </div>

    {{-- ── Body ──────────────────────────────────────────────────────── --}}
    <div class="body">

        {{-- ─ License Expiry ─────────────────────────────────────────── --}}
        @if($type === 'license_expiry_reminder')
            @php $urgency = $data['urgency'] ?? 'MEDIUM'; @endphp
            <div class="alert-box {{ $urgency === 'CRITICAL' ? 'critical' : ($urgency === 'HIGH' ? 'high' : '') }}">
                <p>
                    @if($urgency === 'CRITICAL') 🚨 <strong>Critical:</strong>
                    @elseif($urgency === 'HIGH')  ⚠️ <strong>Urgent:</strong>
                    @else 📋 <strong>Reminder:</strong>
                    @endif
                    Driver <strong>{{ $data['driver_name'] ?? 'N/A' }}</strong>'s license expires in
                    <strong>{{ $data['days_until_expiry'] ?? '?' }} day(s)</strong>.
                    Immediate action may be required.
                </p>
            </div>
            <table class="info-table">
                <tr><td class="label">Driver Name</td><td class="value">{{ $data['driver_name'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">License Number</td><td class="value">{{ $data['license_number'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Expiry Date</td><td class="value">{{ $data['expiry_date'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Days Until Expiry</td><td class="value">{{ $data['days_until_expiry'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Urgency Level</td><td class="value">{{ $urgency }}</td></tr>
            </table>

        {{-- ─ Service Alert ───────────────────────────────────────────── --}}
        @elseif($type === 'service_alert')
            <div class="alert-box critical">
                <p>A <strong>{{ strtoupper($data['severity'] ?? 'INFO') }}</strong> service alert has been issued for route <strong>{{ $data['route_name'] ?? 'All Routes' }}</strong>.</p>
            </div>
            <table class="info-table">
                <tr><td class="label">Title</td><td class="value">{{ $data['title'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Route</td><td class="value">{{ $data['route_name'] ?? 'All Routes' }}</td></tr>
                <tr><td class="label">Severity</td><td class="value">{{ strtoupper($data['severity'] ?? 'INFO') }}</td></tr>
                <tr><td class="label">Status</td><td class="value">{{ ucfirst($data['status'] ?? 'N/A') }}</td></tr>
                <tr><td class="label">Message</td><td class="value">{{ $data['message'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Issued At</td><td class="value">{{ $data['created_at'] ?? 'N/A' }}</td></tr>
            </table>

        {{-- ─ Maintenance Completion ─────────────────────────────────── --}}
        @elseif($type === 'maintenance_completion')
            <div class="alert-box">
                <p>Maintenance for bus <strong>{{ $data['bus_plate'] ?? 'N/A' }}</strong> has been completed and inspected.</p>
            </div>
            <table class="info-table">
                <tr><td class="label">Bus Plate</td><td class="value">{{ $data['bus_plate'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Route</td><td class="value">{{ $data['bus_route'] ?? 'Unassigned' }}</td></tr>
                <tr><td class="label">Maintenance Type</td><td class="value">{{ ucfirst($data['maintenance_type'] ?? 'N/A') }}</td></tr>
                <tr><td class="label">Technician</td><td class="value">{{ $data['technician'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Completed At</td><td class="value">{{ $data['completed_at'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Inspection Result</td><td class="value">{{ $data['inspection_result'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Cost</td><td class="value">₱ {{ number_format($data['cost'] ?? 0, 2) }}</td></tr>
            </table>

        {{-- ─ Pending Maintenance Reminder ──────────────────────────── --}}
        @elseif($type === 'pending_maintenance_reminder')
            <div class="alert-box high">
                <p>Scheduled maintenance for bus <strong>{{ $data['bus_plate'] ?? 'N/A' }}</strong> is due in <strong>{{ $data['days_until_due'] ?? '?' }} day(s)</strong>.</p>
            </div>
            <table class="info-table">
                <tr><td class="label">Bus Plate</td><td class="value">{{ $data['bus_plate'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Maintenance Type</td><td class="value">{{ ucfirst($data['maintenance_type'] ?? 'N/A') }}</td></tr>
                <tr><td class="label">Scheduled Date</td><td class="value">{{ $data['scheduled_date'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Days Until Due</td><td class="value">{{ $data['days_until_due'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Expected Duration</td><td class="value">{{ $data['expected_duration'] ?? 'N/A' }}</td></tr>
            </table>

        {{-- ─ Incident Alert ──────────────────────────────────────────── --}}
        @elseif($type === 'incident_alert')
            <div class="alert-box critical">
                <p>🚨 A <strong>{{ strtoupper($data['severity'] ?? 'UNKNOWN') }}</strong> severity incident has been reported. Immediate review is required.</p>
            </div>
            <table class="info-table">
                <tr><td class="label">Incident Type</td><td class="value">{{ ucfirst($data['incident_type'] ?? 'N/A') }}</td></tr>
                <tr><td class="label">Severity</td><td class="value">{{ strtoupper($data['severity'] ?? 'N/A') }}</td></tr>
                <tr><td class="label">Driver</td><td class="value">{{ $data['driver_name'] ?? 'Unknown' }}</td></tr>
                <tr><td class="label">Bus Plate</td><td class="value">{{ $data['bus_plate'] ?? 'Unknown' }}</td></tr>
                <tr><td class="label">Description</td><td class="value">{{ $data['description'] ?? 'N/A' }}</td></tr>
                <tr><td class="label">Occurred At</td><td class="value">{{ $data['occurred_at'] ?? 'N/A' }}</td></tr>
            </table>

        {{-- ─ Generic fallback ──────────────────────────────────────── --}}
        @else
            <div class="alert-box">
                <p>You have a new notification from the GoPasig Fleet Management System.</p>
            </div>
            <table class="info-table">
                @foreach($data as $key => $value)
                    @if(!is_array($value) && $key !== 'notification_type')
                        <tr>
                            <td class="label">{{ ucwords(str_replace('_', ' ', $key)) }}</td>
                            <td class="value">{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif

        <div class="btn-wrap">
            <a class="btn" href="{{ config('app.url') }}/admin">Open Fleet Dashboard →</a>
        </div>

        <hr class="divider">
        <p style="font-size:13px;color:#64748b;text-align:center;">
            This is an automated notification from the GoPasig Fleet Management System.<br>
            Please do not reply to this email.
        </p>
    </div>

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="footer">
        <p>
            © {{ date('Y') }} GoPasig Fleet Management System<br>
            Pasig City Government &mdash; Public Transport Division<br>
            <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
        </p>
    </div>

</div>
</body>
</html>
