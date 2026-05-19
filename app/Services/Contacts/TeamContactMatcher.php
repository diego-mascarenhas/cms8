<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use Illuminate\Support\Collection;

class TeamContactMatcher
{
    public const SEARCH_DEFAULT_LIMIT = 10;

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

        return $this->findExistingByName($base, $name);
    }

    /**
     * @return list<int>
     */
    public function findIdsByName(int $teamId, string $name): array
    {
        $existing = $this->findExisting($teamId, null, null, $name);
        if ($existing)
        {
            return [(int) $existing->id];
        }

        $matches = $this->search($teamId, $name, 3);

        if ($matches->count() === 1)
        {
            return [(int) $matches->first()->id];
        }

        return [];
    }

    /**
     * @return Collection<int, Contact>
     */
    public function search(int $teamId, string $query, int $limit = self::SEARCH_DEFAULT_LIMIT): Collection
    {
        $query = trim($query);
        if ($query === '')
        {
            return collect();
        }

        $limit = max(1, min($limit, 25));

        $existing = $this->findExisting($teamId, null, null, $query);
        if ($existing)
        {
            return collect([$existing]);
        }

        if (str_contains($query, '@'))
        {
            $byEmail = $this->findExisting($teamId, $query, null, null);
            if ($byEmail)
            {
                return collect([$byEmail]);
            }
        }

        $phoneDigits = preg_replace('/[^0-9]/', '', $query) ?: '';
        if (strlen($phoneDigits) >= 6)
        {
            $byPhone = $this->findExisting($teamId, null, (int) $phoneDigits, null);
            if ($byPhone)
            {
                return collect([$byPhone]);
            }
        }

        $term = '%'.mb_strtolower($query).'%';

        return Contact::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where(function ($builder) use ($term, $phoneDigits)
            {
                $builder->whereRaw('LOWER(TRIM(name)) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(surname, ""))) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(TRIM(CONCAT(name, " ", COALESCE(surname, "")))) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(email, ""))) LIKE ?', [$term]);

                if (strlen($phoneDigits) >= 4)
                {
                    $builder->orWhere('phone', 'like', '%'.$phoneDigits.'%');
                }
            })
            ->orderBy('name')
            ->orderBy('surname')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    public function splitFullName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        if ($fullName === '')
        {
            return ['', null];
        }

        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) <= 1)
        {
            return [$fullName, null];
        }

        return [
            $parts[0],
            implode(' ', array_slice($parts, 1)),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Contact>  $base
     */
    private function findExistingByName($base, ?string $name): ?Contact
    {
        $normalizedName = $name !== null ? mb_strtolower(trim($name)) : '';
        if ($normalizedName === '')
        {
            return null;
        }

        $existing = (clone $base)->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->first();
        if ($existing)
        {
            return $existing;
        }

        $existing = (clone $base)
            ->whereRaw('LOWER(TRIM(CONCAT(name, " ", COALESCE(surname, "")))) = ?', [$normalizedName])
            ->first();
        if ($existing)
        {
            return $existing;
        }

        $parts = preg_split('/\s+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2)
        {
            $firstName = $parts[0];
            $surname = implode(' ', array_slice($parts, 1));

            $existing = (clone $base)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$firstName])
                ->whereRaw('LOWER(TRIM(COALESCE(surname, ""))) = ?', [$surname])
                ->first();
            if ($existing)
            {
                return $existing;
            }
        }

        return null;
    }
}
