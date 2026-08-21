<?php

namespace App\Livewire\Teams;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager as JetstreamTeamMemberManager;
use Laravel\Jetstream\Jetstream;

class TeamMemberManager extends JetstreamTeamMemberManager
{
    use PasswordValidationRules;

    public string $roleFilter = 'admin';

    public string $search = '';

    public function mount($team): void
    {
        parent::mount($team);

        if (! empty($this->addTeamMemberForm['role']))
        {
            return;
        }

        $defaultRole = collect(Jetstream::$roles)->first(fn ($role) => $role->key === 'admin')
            ?? collect(Jetstream::$roles)->first();

        if ($defaultRole)
        {
            $this->addTeamMemberForm['role'] = $defaultRole->key;
        }
    }

    /**
     * Persist the selected Jetstream team role, then keep the member visible in the list.
     */
    public function updateRole(UpdateTeamMemberRole $updater): void
    {
        $updater->update(
            $this->user,
            $this->team,
            $this->managingRoleFor->id,
            $this->currentRole,
        );

        $this->team = $this->team->fresh();
        $this->roleFilter = $this->currentRole !== '' && $this->currentRole !== null
            ? $this->currentRole
            : 'all';
        $this->stopManagingRole();
    }

    /**
     * Set a new password for a member of this team.
     */
    public function updateMemberPassword(int $userId, string $password, string $passwordConfirmation): void
    {
        Gate::forUser($this->user)->authorize('updateTeamMember', $this->team);

        $member = Jetstream::findUserByIdOrFail($userId);

        if (! $member->belongsToTeam($this->team))
        {
            abort(403);
        }

        Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => $this->passwordRules(),
        ])->validate();

        $member->forceFill([
            'password' => Hash::make($password),
        ])->save();

        Log::info('Team member password updated', [
            'team_id' => $this->team->id,
            'member_id' => $member->id,
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * Team members filtered by the selected membership role.
     */
    public function getFilteredMembersProperty(): Collection
    {
        $query = $this->team->users()->orderBy('users.name');

        if ($this->roleFilter !== 'all')
        {
            $query->wherePivot('role', $this->roleFilter);
        }

        $search = trim($this->search);

        if ($search !== '')
        {
            $query->where(function ($builder) use ($search)
            {
                $builder->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Total number of members in the team, ignoring the active filter.
     */
    public function getTotalMembersCountProperty(): int
    {
        return $this->team->users()->count();
    }

    /**
     * Options for the role filter dropdown.
     *
     * @return \Illuminate\Support\Collection<int, \Laravel\Jetstream\Role>
     */
    public function getRoleFilterOptionsProperty(): Collection
    {
        return collect(Jetstream::$roles);
    }
}
