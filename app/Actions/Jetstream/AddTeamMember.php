<?php

namespace App\Actions\Jetstream;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddTeamMember implements AddsTeamMembers
{
    public function __construct(private JetstreamTeamRoleSynchronizer $roleSynchronizer) {}

    /**
     * Add a new team member to the given team.
     */
    public function add(User $user, Team $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        $newTeamMember = Jetstream::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($team, $newTeamMember);

        $team->users()->attach(
            $newTeamMember,
            ['role' => $role],
        );

        $this->roleSynchronizer->sync($newTeamMember, $role);

        TeamMemberAdded::dispatch($team, $newTeamMember);
    }

    /**
     * Validate the add member operation.
     */
    protected function validate(Team $team, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules(), [
            'email.exists' => __('We were unable to find a registered user with this email address.'),
        ])->after(function ($validator) use ($team, $email, $role): void
        {
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)($validator);
            $this->ensureLinkedContactKeepsClientRole($email, $role)($validator);
        })->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a team member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users'],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Client portal users linked to a contact must keep the Client team role.
     */
    protected function ensureLinkedContactKeepsClientRole(string $email, ?string $role): Closure
    {
        return function ($validator) use ($email, $role)
        {
            if ($role === null || $role === '' || $role === 'client')
            {
                return;
            }

            $member = User::query()->where('email', $email)->first();

            if ($member === null || ! $member->hasRole('client'))
            {
                return;
            }

            $linkedContact = Contact::withoutGlobalScopes()
                ->where('user_id', $member->id)
                ->first();

            if ($linkedContact === null)
            {
                return;
            }

            $validator->errors()->add(
                'email',
                __('This user is linked to a client contact and must use the Client role.'),
            );
        };
    }

    /**
     * Ensure that the user is not already on the team.
     */
    protected function ensureUserIsNotAlreadyOnTeam(Team $team, string $email): Closure
    {
        return function ($validator) use ($team, $email)
        {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.'),
            );
        };
    }
}
