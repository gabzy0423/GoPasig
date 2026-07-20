<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    /**
     * Display the announcements index page.
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $filterPriority = $request->input('priority', 'all');
        $filterAudience = $request->input('audience', 'all');
        $filterStatus = $request->input('status', 'all');
        $sortOrder = $request->input('sort', 'newest');

        $routes = Route::getAllCached();
        $announcementStats = $this->getAnnouncementStats();
        $announcements = $this->getFilteredAnnouncements($search, $filterPriority, $filterAudience, $filterStatus, $sortOrder);

        return view('fleet.announcements.index', [
            'search' => $search,
            'filterPriority' => $filterPriority,
            'filterAudience' => $filterAudience,
            'filterStatus' => $filterStatus,
            'sortOrder' => $sortOrder,
            'routes' => $routes,
            'announcementStats' => $announcementStats,
            'announcements' => $announcements,
        ]);
    }

    /**
     * Get JSON Announcements list and stats for AJAX updates.
     */
    public function getAnnouncementsData(Request $request)
    {
        $search = $request->input('search', '');
        $filterPriority = $request->input('priority', 'all');
        $filterAudience = $request->input('audience', 'all');
        $filterStatus = $request->input('status', 'all');
        $sortOrder = $request->input('sort', 'newest');

        $announcementStats = $this->getAnnouncementStats();
        $announcements = $this->getFilteredAnnouncements($search, $filterPriority, $filterAudience, $filterStatus, $sortOrder);

        return response()->json([
            'announcements' => $announcements,
            'announcementStats' => $announcementStats,
        ]);
    }

    /**
     * Retrieve single announcement details.
     */
    public function getDetails($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return response()->json(['success' => false, 'message' => 'Announcement not found'], 404);
        }

        // Format dates for input datetime-local
        return response()->json([
            'success' => true,
            'announcement' => [
                'id' => $announcement->id,
                'headline' => $announcement->headline,
                'body' => $announcement->body,
                'priority' => $announcement->priority,
                'audience' => $announcement->audience,
                'affected_route' => $announcement->affected_route ?: 'All Routes',
                'is_scheduled' => (bool)$announcement->is_scheduled,
                'scheduled_at' => $announcement->scheduled_at ? Carbon::parse($announcement->scheduled_at)->format('Y-m-d\TH:i') : '',
                'expires_at' => $announcement->expires_at ? Carbon::parse($announcement->expires_at)->format('Y-m-d\TH:i') : '',
                'is_draft' => (bool)$announcement->is_draft,
                'posted_by' => $announcement->posted_by,
                'created_at' => Carbon::parse($announcement->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A'),
                'expires_at_formatted' => $announcement->expires_at ? Carbon::parse($announcement->expires_at)->timezone('Asia/Manila')->format('M d, Y h:i A') : null,
                'scheduled_at_formatted' => $announcement->scheduled_at ? Carbon::parse($announcement->scheduled_at)->timezone('Asia/Manila')->format('M d, Y h:i A') : null,
                'status' => $announcement->status,
            ]
        ]);
    }

    /**
     * Create or update announcement.
     */
    public function storeOrUpdate(Request $request)
    {
        $id = $request->input('id');
        $isEditing = !empty($id);

        $rules = [
            'headline' => 'required|string|max:100|min:3',
            'body' => 'required|string|min:5',
            'priority' => 'required|in:Low,Medium,High',
            'audience' => 'required|in:Commuters,Drivers,Administrators,All Users',
            'affected_route' => 'nullable|string',
            'is_scheduled' => 'boolean',
            'scheduled_at' => 'required_if:is_scheduled,true|nullable|date',
            'expires_at' => 'nullable|date',
            'is_draft' => 'boolean',
        ];

        $validated = $request->validate($rules);

        $user = Auth::user();
        $postedBy = $user && $user->name ? $user->name : \App\Models\SystemSetting::get('default_poster_name', 'Fleet Operations');

        $data = [
            'headline' => $validated['headline'],
            'body' => $validated['body'],
            'priority' => $validated['priority'],
            'audience' => $validated['audience'],
            'affected_route' => $validated['affected_route'] === 'All Routes' ? null : $validated['affected_route'],
            'is_scheduled' => (bool)$validated['is_scheduled'],
            'scheduled_at' => $validated['is_scheduled'] && $validated['scheduled_at'] ? Carbon::parse($validated['scheduled_at']) : null,
            'expires_at' => $validated['expires_at'] ? Carbon::parse($validated['expires_at']) : null,
            'is_draft' => (bool)$validated['is_draft'],
            'posted_by' => $postedBy,
        ];

        if ($isEditing) {
            $announcement = Announcement::find($id);
            if (!$announcement) {
                return response()->json(['success' => false, 'message' => 'Announcement not found.'], 404);
            }
            $announcement->update($data);
            $message = 'Announcement updated successfully.';
        } else {
            $announcement = Announcement::create($data);
            $message = 'Announcement posted successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'announcement' => $announcement
        ]);
    }

    /**
     * Delete announcement.
     */
    public function destroy($id)
    {
        $announcement = Announcement::find($id);
        if (!$announcement) {
            return response()->json(['success' => false, 'message' => 'Announcement not found.'], 404);
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully.'
        ]);
    }

    /**
     * Compute statistics properties.
     */
    public function getAnnouncementStats()
    {
        $now = now();
        $active = Announcement::where('is_draft', false)
            ->where(function ($q) use ($now) {
                $q->where('is_scheduled', false)
                    ->orWhere('scheduled_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->count();

        $scheduled = Announcement::where('is_draft', false)
            ->where('is_scheduled', true)
            ->where('scheduled_at', '>', $now)
            ->count();

        $expired = Announcement::where('is_draft', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->count();

        $highPriority = Announcement::where('priority', 'High')
            ->where('is_draft', false)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->count();

        return (object) [
            'active' => $active,
            'scheduled' => $scheduled,
            'expired' => $expired,
            'high_priority' => $highPriority,
        ];
    }

    /**
     * Get filtered paginated announcements.
     */
    public function getFilteredAnnouncements($search, $filterPriority, $filterAudience, $filterStatus, $sortOrder)
    {
        $query = Announcement::query();
        $now = now();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('headline', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%');
            });
        }

        if ($filterPriority !== 'all') {
            $query->where('priority', $filterPriority);
        }

        if ($filterAudience !== 'all') {
            $query->where('audience', $filterAudience);
        }

        if ($filterStatus !== 'all') {
            if ($filterStatus === 'Draft') {
                $query->where('is_draft', true);
            } elseif ($filterStatus === 'Scheduled') {
                $query->where('is_draft', false)
                    ->where('is_scheduled', true)
                    ->where('scheduled_at', '>', $now);
            } elseif ($filterStatus === 'Expired') {
                $query->where('is_draft', false)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $now);
            } elseif ($filterStatus === 'Active') {
                $query->where('is_draft', false)
                    ->where(function ($q) use ($now) {
                        $q->where('is_scheduled', false)
                            ->orWhere('scheduled_at', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now);
                    });
            }
        }

        if ($sortOrder === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate(10);
    }
}
