<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTeamUserRequest;
use App\Http\Requests\Api\UpdateTeamUserPasswordRequest;
use App\Http\Requests\Api\UpdateTeamUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\AssignableTeamUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List users of the authenticated user's current team (for IDONEO app).
     *
     * Query params:
     * - assignable=1: only staff profiles (admin, collaborator, editor, etc.). Excludes clients.
     * - admins=1: team owner plus users with the admin role.
     * - assistant=1 / basic=1: team owner plus admin and collaborator profiles.
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
            'users' => $teamUsers->map(fn (User $teamUser) => $this->presentUser($teamUser, $team))->values(),
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
                'message' => __('No tenés permiso para agregar usuarios.'),
            ], 403);
        }

        $validated = $request->validated();
        $role = $validated['role'] ?? 'admin';
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
        $user->teams()->syncWithoutDetaching([$team->id => ['role' => $role]]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->load('roles');

        return response()->json([
            'success' => true,
            'user' => $this->presentUser($user, $team),
        ], 201);
    }

    public function update(UpdateTeamUserRequest $request, User $user): JsonResponse
    {
        $managed = $this->managedTeamUser($request, $user);
        if ($managed instanceof JsonResponse)
        {
            return $managed;
        }

        [, $team] = $managed;
        $validated = $request->validated();
        $isOwner = (int) $team->user_id === (int) $user->id;

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password']))
        {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (! $isOwner && ! empty($validated['role']))
        {
            $role = $validated['role'];
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->syncRoles([$role]);
            $user->teams()->updateExistingPivot($team->id, ['role' => $role]);
        }

        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => __('Usuario actualizado.'),
            'user' => $this->presentUser($user, $team),
        ]);
    }

    public function updatePassword(UpdateTeamUserPasswordRequest $request, User $user): JsonResponse
    {
        $managed = $this->managedTeamUser($request, $user);
        if ($managed instanceof JsonResponse)
        {
            return $managed;
        }

        $user->forceFill([
            'password' => Hash::make($request->validated()['password']),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => __('Contraseña actualizada.'),
        ]);
    }

    public function sendPasswordReset(Request $request, User $user): JsonResponse
    {
        $managed = $this->managedTeamUser($request, $user);
        if ($managed instanceof JsonResponse)
        {
            return $managed;
        }

        $request->validate([
            'frontend_url' => ['nullable', 'string', 'max:255'],
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return response()->json([
            'success' => true,
            'message' => __('Enviamos un enlace para restablecer la contraseña.'),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $managed = $this->managedTeamUser($request, $user);
        if ($managed instanceof JsonResponse)
        {
            return $managed;
        }

        [$actor, $team] = $managed;

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
        return $actor->canManageTeam($team);
    }

    /**
     * @return array{0: User, 1: Team}|JsonResponse
     */
    private function managedTeamUser(Request $request, User $user): array|JsonResponse
    {
        $actor = $request->user();
        $team = $this->currentTeam($actor);
        if (! $actor || ! $team)
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
                'message' => __('No tenés permiso para gestionar usuarios.'),
            ], 403);
        }

        if (! $team->allUsers()->contains('id', $user->id))
        {
            return response()->json([
                'success' => false,
                'message' => __('Ese usuario no pertenece a este equipo.'),
            ], 404);
        }

        return [$actor, $team];
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
        if ($request->boolean('assistant') || $request->boolean('basic'))
        {
            $ownerId = (int) $team->user_id;

            return $users
                ->filter(function (User $teamUser) use ($ownerId)
                {
                    return ($ownerId > 0 && (int) $teamUser->id === $ownerId)
                        || $teamUser->hasAnyRole(['admin', 'root', 'collaborator']);
                })
                ->values();
        }

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
     * @return array{id: int, name: string, email: string, role: ?string, roles: list<string>, is_owner: bool}
     */
    private function presentUser(User $user, Team $team): array
    {
        $roleNames = $user->roles->pluck('name')->values()->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $roleNames[0] ?? 'admin',
            'roles' => $roleNames !== [] ? $roleNames : ['admin'],
            'is_owner' => (int) $team->user_id === (int) $user->id,
        ];
    }
}
