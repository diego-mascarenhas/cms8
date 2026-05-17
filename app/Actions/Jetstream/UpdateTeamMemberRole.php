<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use App\Support\JetstreamTeamRoleSynchronizer;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    public function __construct(private JetstreamTeamRoleSynchronizer $roleSynchronizer) {}

    /**
     * Update the role for the given team member and sync Spatie role when mapped.
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        parent::update($user, $team, $teamMemberId, $role);

        $member = User::find($teamMemberId);
        if ($member)
        {
            $this->roleSynchronizer->sync($member, $role);
        }
    }
}
