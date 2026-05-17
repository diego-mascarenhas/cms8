<?php

namespace App\Actions\Jetstream;

use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;

class AcceptTeamInvitation
{
    public function __construct(private JetstreamTeamRoleSynchronizer $roleSynchronizer) {}

    /**
     * Attach the user to the invited team (no personal team) and set it as current.
     */
    public function accept(User $user, TeamInvitation $invitation): void
    {
        if (strcasecmp((string) $user->email, (string) $invitation->email) !== 0)
        {
            throw ValidationException::withMessages([
                'email' => __('This invitation was sent to a different email address.'),
            ]);
        }

        $team = $invitation->team;

        DB::transaction(function () use ($user, $invitation, $team): void
        {
            if (! $team->hasUserWithEmail($user->email))
            {
                AddingTeamMember::dispatch($team, $user);

                $team->users()->attach($user, ['role' => $invitation->role]);

                $this->roleSynchronizer->sync($user, $invitation->role);

                TeamMemberAdded::dispatch($team, $user);
            }

            $user->forceFill(['current_team_id' => $team->id])->save();

            $invitation->delete();
        });

        $user->refresh();
    }
}
