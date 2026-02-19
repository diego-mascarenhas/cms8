<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole as JetstreamUpdateTeamMemberRole;
use Laravel\Jetstream\Jetstream;

class UpdateTeamMemberRole extends JetstreamUpdateTeamMemberRole
{
    /**
     * Jetstream team role key => Spatie role name (only these are synced).
     */
    protected static array $roleMap = [
        'admin' => 'admin',
        'editor' => 'editor',
        'collaborator' => 'collaborator',
        'employee' => 'employee',
    ];

    /**
     * Update the role for the given team member and sync Spatie role when mapped.
     */
    public function update($user, $team, $teamMemberId, string $role): void
    {
        parent::update($user, $team, $teamMemberId, $role);

        $member = User::find($teamMemberId);
        if ($member)
        {
            $this->syncSpatieRole($member, $role);
        }
    }

    protected function syncSpatieRole(User $member, string $jetstreamRole): void
    {
        if (! isset(static::$roleMap[$jetstreamRole]))
        {
            return;
        }

        $spatieRole = static::$roleMap[$jetstreamRole];

        foreach (array_keys(static::$roleMap) as $mappedRole)
        {
            if ($mappedRole === $spatieRole)
            {
                continue;
            }
            try
            {
                $member->removeRole($mappedRole);
            } catch (\Throwable)
            {
            }
        }

        try
        {
            if (! $member->hasRole($spatieRole))
            {
                $member->assignRole($spatieRole);
            }
        } catch (\Throwable)
        {
        }
    }
}
