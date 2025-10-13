<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Display team information and statistics.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');

        // Get team statistics
        $stats = [
            'contacts' => Contact::where('team_id', $team->id)->count(),
            'projects' => Project::where('team_id', $team->id)->count(),
            'tasks' => Task::where('team_id', $team->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'personal_team' => $team->personal_team,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ],
            'statistics' => $stats,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get team settings (non-sensitive data only).
     */
    public function settings(Request $request)
    {
        $team = $request->attributes->get('team');

        // Get non-sensitive settings
        $settings = $team->settings()
            ->where('is_encrypted', false)
            ->whereNotIn('key', ['api_token_hash', 'stripe_secret', 'stripe_webhook'])
            ->get()
            ->groupBy('group');

        return response()->json([
            'success' => true,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'settings' => $settings,
        ]);
    }
}
