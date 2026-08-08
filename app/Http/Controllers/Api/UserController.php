<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AssignableTeamUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    /**
     * List users of the authenticated user's current team (for IDONEO app).
     *
     * Query params:
     * - assignable=1: only staff profiles (admin, collaborator, editor, etc.). Excludes clients.
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

        /** @var Collection<int, User> $teamUsers */
        $teamUsers = $request->boolean('assignable')
            ? AssignableTeamUsers::forTeam($team)
            : $team->allUsers()->load('roles')->sortBy('name')->values();

        $users = $teamUsers
            ->map(function (User $teamUser)
            {
                $roleNames = $teamUser->roles->pluck('name')->values()->all();

                return [
                    'id' => $teamUser->id,
                    'name' => $teamUser->name,
                    'email' => $teamUser->email,
                    'role' => $roleNames[0] ?? null,
                    'roles' => $roleNames,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }
}
