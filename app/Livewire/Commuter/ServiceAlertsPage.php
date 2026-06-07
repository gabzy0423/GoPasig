<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\ServiceAlert;
use App\Models\ServiceAlertRead;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ServiceAlertsPage extends Component
{
    public $filter = 'all';
    public $alertCount = 0;
    public $showResolved = false;
    public $expandedAlerts = [];
    public $readAlerts = []; // session-backed tracker for unread states

    public function mount()
    {
        // Kunin ang records sa database
        $query = ServiceAlertRead::query();

        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        } else {
            $query->where('session_id', session()->getId());
        }

        $dbReads = $query->pluck('service_alert_id')->toArray();
        $sessionReads = session()->get('read_alerts', []);

        $this->readAlerts = array_unique(array_merge($dbReads, $sessionReads));
    }

    public function filterAlerts($type)
    {
        $this->filter = $type;
    }

    public function markRead($id)
    {
        if (!in_array($id, $this->readAlerts)) {
            $this->readAlerts[] = $id;

            // I-save sa database para mag-persist
            ServiceAlertRead::firstOrCreate([
                'service_alert_id' => $id,
                'user_id' => Auth::id(),
                'session_id' => Auth::check() ? null : session()->getId(),
            ], [
                'read_at' => now()
            ]);

            // Keep in session for immediate redundancy
            session()->put('read_alerts', $this->readAlerts);
        }
    }

    public function toggleResolved()
    {
        $this->showResolved = !$this->showResolved;
    }

    public function toggleExpand($id)
    {
        if (in_array($id, $this->expandedAlerts)) {
            $this->expandedAlerts = array_diff($this->expandedAlerts, [$id]);
        } else {
            $this->expandedAlerts[] = $id;
        }
    }

    public function render()
    {
        $routeColors = \App\Models\Route::pluck('color', 'name')->toArray();

        // 1. Fetch active alerts from database
        $activeQuery = ServiceAlert::where('status', 'active');

        if ($this->filter !== 'all') {
            // Map filter types from pills to seeder values
            $typeMap = [
                'delays' => 'delay',
                'route changes' => 'route_change',
                'suspensions' => 'suspension',
                'maintenance' => 'maintenance',
            ];
            $type = $typeMap[$this->filter] ?? $this->filter;
            $activeQuery->where('type', $type);
        }

        $dbActiveAlerts = $activeQuery->get();

        // 2. Map alerts to collection structure
        $activeAlertsList = $dbActiveAlerts->map(function ($alert) use ($routeColors) {
            $routesList = [];
            if ($alert->affected_routes) {
                $names = array_map('trim', explode(',', $alert->affected_routes));
                foreach ($names as $name) {
                    $routesList[] = [
                        'name' => $name,
                        'color' => $routeColors[$name] ?? '#003F87',
                    ];
                }
            }

            $isRead = in_array($alert->id, $this->readAlerts);

            // Resumption estimations from database
            $estResumption = $alert->estimated_resumption;

            return (object) [
                'id' => $alert->id,
                'type' => $alert->type,
                'headline' => $alert->title,
                'message' => $alert->message,
                'affected_routes' => $routesList,
                'posted_at' => Carbon::parse($alert->created_at)->diffForHumans(),
                'estimated_resumption' => $estResumption,
                'is_read' => $isRead,
            ];
        });

        // Sorting: Unread alerts surface above read alerts, then sorted newest first
        $activeAlertsMapped = $activeAlertsList->sort(function ($a, $b) {
            if ($a->is_read === $b->is_read) {
                return $b->id <=> $a->id; // Newest first
            }
            return $a->is_read <=> $b->is_read; // Unread (false) first
        })->values();

        // Mapped counts
        $this->alertCount = $activeAlertsMapped->count();

        // Unread badge count (global count of unread active alerts)
        $unreadCount = ServiceAlert::where('status', 'active')->get()->filter(function ($alert) {
            return !in_array($alert->id, $this->readAlerts);
        })->count();

        // 3. Fetch resolved alerts from database
        $dbResolvedAlerts = ServiceAlert::where('status', 'resolved')->get();

        $resolvedAlertsMapped = $dbResolvedAlerts->map(function ($alert) use ($routeColors) {
            $routesList = [];
            if ($alert->affected_routes) {
                $names = array_map('trim', explode(',', $alert->affected_routes));
                foreach ($names as $name) {
                    $routesList[] = [
                        'name' => $name,
                        'color' => $routeColors[$name] ?? '#003F87',
                    ];
                }
            }

            return (object) [
                'id' => $alert->id,
                'type' => $alert->type,
                'headline' => $alert->title ?? ($alert->headline ?? 'Alert resolved'),
                'message' => $alert->message,
                'affected_routes' => $routesList,
                'posted_at' => Carbon::parse($alert->created_at)->diffForHumans(),
                'estimated_resumption' => null,
                'is_read' => true,
                'resolved_at' => $alert->resolved_at ?? ($alert->updated_at ? Carbon::parse($alert->updated_at)->diffForHumans() : 'Recently'),
            ];
        })->sortByDesc('id')->values();

        return view('livewire.commuter.service-alerts-page', [
            'activeAlerts' => $activeAlertsMapped,
            'resolvedAlerts' => $resolvedAlertsMapped,
            'unreadCount' => $unreadCount,
            'resolvedCount' => $resolvedAlertsMapped->count(),
        ]);
    }
}
