<?php

namespace App\Actions\Fortify;

use App\Actions\Jetstream\AcceptTeamInvitation;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamModulesByPricingPlanSyncer;
use App\Support\PendingTeamInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private AcceptTeamInvitation $acceptTeamInvitation,
        private TeamModulesByPricingPlanSyncer $teamModulesByPricingPlanSyncer,
    ) {}

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $pendingInvitation = PendingTeamInvitation::get(request());

        $emailRules = ['required', 'string', 'email', 'max:255', 'unique:users'];

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => array_merge($emailRules, $pendingInvitation ? [
                function (string $attribute, mixed $value, \Closure $fail) use ($pendingInvitation): void
                {
                    if (strcasecmp((string) $value, (string) $pendingInvitation->email) !== 0)
                    {
                        $fail(__('This invitation was sent to a different email address.'));
                    }
                },
            ] : []),
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return User::query()->getModel()->getConnection()->transaction(function () use ($input, $pendingInvitation)
        {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            if ($pendingInvitation)
            {
                $user->forceFill(['email_verified_at' => now()])->save();

                PendingTeamInvitation::pull(request());
                $this->acceptTeamInvitation->accept($user, $pendingInvitation);
            } else
            {
                $this->createPersonalTeam($user);
            }

            $user->refresh();

            return $user;
        });
    }

    /**
     * Create a personal team for self-registered users.
     */
    protected function createPersonalTeam(User $user): void
    {
        $team = $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));

        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        $planSlug = (string) config('humano_pricing.registration_team_plan_slug', 'hunter');
        $this->teamModulesByPricingPlanSyncer->syncForHumanoPricingPlan($team, $planSlug);
    }
}
