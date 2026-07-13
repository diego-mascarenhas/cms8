<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    public function __construct(private JetstreamTeamRoleSynchronizer $roleSynchronizer) {}

    /**
     * Update the role for the given team member and sync Spatie role when mapped.
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        $member = User::query()->find($teamMemberId);

        if ($member !== null)
        {
            $linkedContact = $member->contact()->withoutGlobalScopes()->first();

            if ($linkedContact !== null && $role !== 'client')
            {
                throw ValidationException::withMessages([
                    'role' => [__('This user is linked to a client contact and must keep the Client role.')],
                ])->errorBag('updateTeamMember');
            }
        }

        parent::update($user, $team, $teamMemberId, $role);

        if ($member)
        {
            $this->roleSynchronizer->sync($member->fresh(), $role);
        }
    }
}
