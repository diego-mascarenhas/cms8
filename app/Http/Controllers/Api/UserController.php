<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTeamUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\AssignableTeamUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List users of the authenticated user's current team (for IDONEO app).
     *
     * Query params:
     * - assignable=1: only staff profiles (admin, collaborator, editor, etc.). Excludes clients.
     * - admins=1: team owner plus users with the admin role.
     */
    public function index(Request $request): JsonResponse
    {
        $team = $this->currentTeam($request->user());
        if (! $team)
        {
            return response()->json(['success' => true, 'users' => []]);
        }

        /** @var Collection<int, User> $teamUsers */
        $teamUsers = $this->teamUsers($team, $request);

        return response()->json([
            'success' => true,
            'users' => $teamUsers->map(fn (User $teamUser) => $this->presentUser($teamUser))->values(),
        ]);
    }

    public function store(StoreTeamUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        $team = $this->currentTeam($actor);
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        if (! $this->canManageTeamUsers($actor, $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés permiso para agregar administradores.'),
            ], 403);
        }

        $validated = $request->validated();
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $user->teams()->syncWithoutDetaching([$team->id => ['role' => 'admin']]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'user' => $this->presentUser($user),
        ], 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $team = $this->currentTeam($actor);
        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => __('No hay equipo actual.'),
            ], 422);
        }

        if (! $this->canManageTeamUsers($actor, $team))
        {
            return response()->json([
                'success' => false,
                'message' => __('No tenés permiso para quitar administradores.'),
            ], 403);
        }

        if ((int) $user->id === (int) $actor->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('No podés quitarte a vos mismo.'),
            ], 422);
        }

        if ((int) $team->user_id === (int) $user->id)
        {
            return response()->json([
                'success' => false,
                'message' => __('No se puede quitar al dueño del equipo.'),
            ], 422);
        }

        if (! $team->allUsers()->contains('id', $user->id))
        {
            return response()->json([
                'success' => false,
                'message' => __('Ese usuario no pertenece a este equipo.'),
            ], 404);
        }

        $user->teams()->detach($team->id);

        if ((int) $user->current_team_id === (int) $team->id)
        {
            $nextTeamId = $user->teams()->value('teams.id');
            $user->forceFill(['current_team_id' => $nextTeamId])->save();
        }

        return response()->json([
            'success' => true,
            'message' => __('Usuario quitado del equipo.'),
        ]);
    }

    private function currentTeam(?User $user): ?Team
    {
        if (! $user)
        {
            return null;
        }

        $user->loadMissing('currentTeam');

        return $user->currentTeam ?? $user->teams()->first();
    }

    private function canManageTeamUsers(User $actor, Team $team): bool
    {
        return (int) $team->user_id === (int) $actor->id
            || $actor->hasRole('admin');
    }

    /**
     * @return Collection<int, User>
     */
    private function teamUsers(Team $team, Request $request): Collection
    {
        if ($request->boolean('assignable'))
        {
            return AssignableTeamUsers::forTeam($team);
        }

        $users = $team->allUsers()->load('roles')->sortBy('name')->values();
        if (! $request->boolean('admins'))
        {
            return $users;
        }

        $ownerId = (int) $team->user_id;

        return $users
            ->filter(function (User $teamUser) use ($ownerId)
            {
                return ($ownerId > 0 && (int) $teamUser->id === $ownerId)
                    || $teamUser->hasRole('admin');
            })
            ->values();
    }

    /**
     * @return array{id: int, name: string, email: string, role: ?string, roles: list<string>}
     */
    private function presentUser(User $user): array
    {
        $roleNames = $user->roles->pluck('name')->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $roleNames[0] ?? 'admin',
            'roles' => $roleNames !== [] ? $roleNames : ['admin'],
        ];
    }
}
