<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class JetstreamTeamRoleSynchronizer
{
    /**
     * Jetstream team role key => Spatie role name.
     *
     * @var array<string, string>
     */
    public const ROLE_MAP = [
        'admin' => 'admin',
        'editor' => 'editor',
        'collaborator' => 'collaborator',
        'developer' => 'developer',
        'technical' => 'technical',
        'employee' => 'employee',
        'client' => 'client',
        'auditor' => 'auditor',
        'guest' => 'guest',
        'user' => 'user',
    ];

    /**
     * Replace mapped Spatie roles so they match the Jetstream team role.
     */
    public function sync(User $user, ?string $jetstreamRole): void
    {
        if ($jetstreamRole === null || $jetstreamRole === '')
        {
            return;
        }

        if (! isset(self::ROLE_MAP[$jetstreamRole]))
        {
            return;
        }

        $targetSpatieRole = self::ROLE_MAP[$jetstreamRole];

        foreach (array_unique(array_values(self::ROLE_MAP)) as $spatieRole)
        {
            if ($spatieRole === $targetSpatieRole)
            {
                continue;
            }

            try
            {
                if ($user->hasRole($spatieRole))
                {
                    $user->removeRole($spatieRole);
                }
            } catch (\Throwable)
            {
            }
        }

        if ($user->hasRole($targetSpatieRole))
        {
            return;
        }

        if (Role::query()->where('name', $targetSpatieRole)->where('guard_name', 'web')->doesntExist())
        {
            return;
        }

        try
        {
            $user->assignRole($targetSpatieRole);
        } catch (\Throwable)
        {
        }
    }
}
