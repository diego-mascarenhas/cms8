<?php

namespace App\Services\Contacts;

use App\Models\Contact;

class TeamContactMatcher
{
    public function findExisting(int $teamId, ?string $email, ?int $phone, ?string $name): ?Contact
    {
        $base = Contact::withoutGlobalScopes()->where('team_id', $teamId);

        $normalizedEmail = $email !== null ? strtolower(trim($email)) : '';
        if ($normalizedEmail !== '')
        {
            $existing = (clone $base)->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->first();
            if ($existing)
            {
                return $existing;
            }
        }

        if ($phone !== null && $phone > 0)
        {
            $existing = (clone $base)->where('phone', $phone)->first();
            if ($existing)
            {
                return $existing;
            }
        }

        $normalizedName = $name !== null ? mb_strtolower(trim($name)) : '';
        if ($normalizedName !== '')
        {
            $existing = (clone $base)->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->first();
            if ($existing)
            {
                return $existing;
            }
        }

        return null;
    }
}
