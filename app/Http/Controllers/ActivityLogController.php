<?php

namespace App\Http\Controllers;

use App\DataTables\ActivityLogDataTable;
use App\Helpers\ActivityHelper;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity logs.
     */
    public function index(ActivityLogDataTable $dataTable)
    {
        // Log this page access
        ActivityHelper::log('Viewed activity log page');

        return $dataTable->render('activity-log.index');
    }

    /**
     * Show the specified activity log.
     */
    public function show(Activity $activity)
    {
        // Check if user can view this activity (same team)
        if (auth()->user()->currentTeam) {
            $teamUserIds = auth()->user()->currentTeam->users->pluck('id');
            if (!$teamUserIds->contains($activity->causer_id)) {
                abort(403, 'Unauthorized to view this activity.');
            }
        }

        return response()->json([
            'activity' => $activity->load(['causer', 'subject']),
            'properties' => $activity->properties
        ]);
    }

    /**
     * Get user-specific activities (for profile page or user detail)
     */
    public function userActivities(Request $request, $userId)
    {
        // Check if user can view activities for this user
        if (auth()->user()->currentTeam) {
            $teamUserIds = auth()->user()->currentTeam->users->pluck('id');
            if (!$teamUserIds->contains($userId)) {
                abort(403, 'Unauthorized to view this user activities.');
            }
        }

        $activities = Activity::where('causer_id', $userId)
            ->with(['subject'])
            ->latest()
            ->paginate(20);

        return response()->json($activities);
    }

    /**
     * Get recent activities for dashboard widget
     */
    public function recent(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $query = Activity::with(['causer', 'subject'])
            ->latest();

        // Filter by team if user has currentTeam
        if (auth()->check() && auth()->user()->currentTeam) {
            $teamUserIds = auth()->user()->currentTeam->users->pluck('id');
            $query->whereIn('causer_id', $teamUserIds);
        }

        $activities = $query->limit($limit)->get();

        return response()->json($activities);
    }

    /**
     * Get activity statistics for dashboard
     */
    public function statistics(Request $request)
    {
        $teamUserIds = collect();
        
        if (auth()->check() && auth()->user()->currentTeam) {
            $teamUserIds = auth()->user()->currentTeam->users->pluck('id');
        }

        // Today's activities
        $todayActivities = Activity::whereIn('causer_id', $teamUserIds)
            ->whereDate('created_at', today())
            ->count();

        // This week's activities  
        $weekActivities = Activity::whereIn('causer_id', $teamUserIds)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // This month's activities
        $monthActivities = Activity::whereIn('causer_id', $teamUserIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Most active users
        $mostActiveUsers = Activity::whereIn('causer_id', $teamUserIds)
            ->with('causer')
            ->select('causer_id')
            ->selectRaw('count(*) as activity_count')
            ->groupBy('causer_id')
            ->orderBy('activity_count', 'desc')
            ->limit(5)
            ->get();

        // Activity types
        $activityTypes = Activity::whereIn('causer_id', $teamUserIds)
            ->select('description')
            ->selectRaw('count(*) as count')
            ->groupBy('description')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'today' => $todayActivities,
            'week' => $weekActivities,
            'month' => $monthActivities,
            'most_active_users' => $mostActiveUsers,
            'activity_types' => $activityTypes,
        ]);
    }
} 