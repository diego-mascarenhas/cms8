<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    /**
     * Staff-like Spatie roles that can be assigned as project/task responsible.
     *
     * @var list<string>
     */
    private const ASSIGNABLE_ROLES = [
        'root',
        'admin',
        'collaborator',
        'editor',
        'developer',
        'technical',
        'employee',
    ];

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
        $teamUsers = $team->allUsers()->load('roles')->sortBy('name')->values();

        if ($request->boolean('assignable'))
        {
            $ownerId = (int) $team->user_id;

            $teamUsers = $teamUsers
                ->filter(function (User $teamUser) use ($ownerId)
                {
                    if ($ownerId > 0 && (int) $teamUser->id === $ownerId)
                    {
                        return true;
                    }

                    return $teamUser->hasAnyRole(self::ASSIGNABLE_ROLES);
                })
                ->values();
        }

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
