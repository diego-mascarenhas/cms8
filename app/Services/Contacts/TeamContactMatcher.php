<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Support\SearchNormalizer;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

class TeamContactMatcher
{
    public const SEARCH_DEFAULT_LIMIT = 10;

    public function findExisting(int $teamId, ?string $email, ?int $phone, ?string $name, ?string $surname = null): ?Contact
    {
        $base = Contact::withoutGlobalScopes()->where('team_id', $teamId);
        $connection = $this->connection();

        $normalizedEmail = $email !== null ? strtolower(trim($email)) : '';
        if ($normalizedEmail !== '')
        {
            $existing = (clone $base)
                ->whereRaw(SearchNormalizer::contactTextColumnLowerSql('email', $connection).' = ?', [$normalizedEmail])
                ->first();
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

        return $this->findExistingByName($base, $name, $surname, $connection);
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
        $connection = $this->connection();

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
            ->where(function ($builder) use ($term, $phoneDigits, $connection)
            {
                $builder->whereRaw(SearchNormalizer::contactTextColumnLowerSql('name', $connection).' like ?', [$term])
                    ->orWhereRaw(SearchNormalizer::contactTextColumnLowerSql('surname', $connection).' like ?', [$term])
                    ->orWhereRaw(SearchNormalizer::contactFullNameLowerSql($connection).' like ?', [$term])
                    ->orWhereRaw(SearchNormalizer::contactTextColumnLowerSql('email', $connection).' like ?', [$term]);

                if (strlen($phoneDigits) >= 4)
                {
                    $builder->orWhereRaw(SearchNormalizer::contactPhoneLikeSql($connection).' like ?', ['%'.$phoneDigits.'%']);
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
    private function findExistingByName($base, ?string $name, ?string $surname, Connection $connection): ?Contact
    {
        $normalizedName = $name !== null ? mb_strtolower(trim($name)) : '';
        $normalizedSurname = $surname !== null ? mb_strtolower(trim($surname)) : '';

        if ($normalizedName === '' && $normalizedSurname === '')
        {
            return null;
        }

        $nameLower = SearchNormalizer::contactTextColumnLowerSql('name', $connection);
        $surnameLower = SearchNormalizer::contactTextColumnLowerSql('surname', $connection);

        if ($normalizedName !== '' && $normalizedSurname !== '')
        {
            $existing = (clone $base)
                ->whereRaw($nameLower.' = ?', [$normalizedName])
                ->whereRaw($surnameLower.' = ?', [$normalizedSurname])
                ->first();
            if ($existing)
            {
                return $existing;
            }

            return null;
        }

        if ($normalizedName === '')
        {
            return null;
        }

        $existing = (clone $base)
            ->whereRaw($nameLower.' = ?', [$normalizedName])
            ->where(function ($builder) use ($surnameLower)
            {
                $builder->whereNull('surname')
                    ->orWhereRaw($surnameLower.' = ?', ['']);
            })
            ->first();
        if ($existing)
        {
            return $existing;
        }

        $existing = (clone $base)
            ->whereRaw(SearchNormalizer::contactFullNameLowerSql($connection).' = ?', [$normalizedName])
            ->first();
        if ($existing)
        {
            return $existing;
        }

        $parts = preg_split('/\s+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 2)
        {
            $firstName = $parts[0];
            $nameSurname = implode(' ', array_slice($parts, 1));

            $existing = (clone $base)
                ->whereRaw($nameLower.' = ?', [$firstName])
                ->whereRaw($surnameLower.' = ?', [$nameSurname])
                ->first();
            if ($existing)
            {
                return $existing;
            }
        }

        return null;
    }

    private function connection(): Connection
    {
        $connection = Contact::query()->getConnection();

        if (! $connection instanceof Connection)
        {
            throw new \LogicException('TeamContactMatcher expects Illuminate\Database\Connection.');
        }

        return $connection;
    }
}
