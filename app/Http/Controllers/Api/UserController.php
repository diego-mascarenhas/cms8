<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users of the authenticated user's current team (for IDONEO app).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $user->load('currentTeam');

        $team = $user->currentTeam;
        if (! $team)
        {
            $team = $user->teams()->first();
        }

        if (! $team)
        {
            return response()->json(['success' => true, 'users' => []]);
        }

        $users = \App\Models\User::query()
            ->whereHas('teams', function ($q) use ($team)
            {
                $q->where('team_id', $team->id);
            })
            ->with(['roles' => function ($q)
            {
                $q->select('name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($u)
            {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->roles->first()->name ?? null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }
}
