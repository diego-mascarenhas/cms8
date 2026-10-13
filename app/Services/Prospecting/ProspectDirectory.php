<?php

namespace App\Services\Prospecting;

use App\Models\Contact;
use App\Models\Person;
use App\Models\PersonEmail;
use App\Models\PersonEnrichment;
use App\Models\ProspectSearch;
use App\Models\ProspectSearchResult;
use App\Models\Team;
use Illuminate\Support\Carbon;

class ProspectDirectory
{
    public const SOURCE_APOLLO = 'apollo';

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array<string, mixed>>  $people
     */
    public function recordSearch(Team $team, int $userId, array $filters, int $page, int $perPage, array $people): ProspectSearch
    {
        $search = ProspectSearch::query()->create([
            'team_id' => $team->id,
            'user_id' => $userId,
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        foreach ($people as $person)
        {
            $externalId = trim((string) ($person['id'] ?? ''));
            if ($externalId === '')
            {
                continue;
            }

            $search->results()->create([
                'source' => self::SOURCE_APOLLO,
                'external_id' => $externalId,
            ]);
        }

        return $search;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedPayload(string $source, string $externalId): ?array
    {
        $enrichment = PersonEnrichment::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->whereNotNull('person_id')
            ->first();

        if ($enrichment === null || ! is_array($enrichment->payload) || $enrichment->payload === [])
        {
            return null;
        }

        return $enrichment->payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeImportedContact(
        Contact $contact,
        int $teamId,
        string $source,
        string $externalId,
        array $payload,
        ?string $organizationName,
        ?int $prospectSearchId,
    ): ?Person {
        $email = $this->normalizeEmail($contact->email);
        $person = $this->findPerson($source, $externalId, $email);

        if ($email !== null)
        {
            if ($person === null)
            {
                $person = Person::query()->create([
                    'name' => $contact->name !== '' ? $contact->name : 'Contact',
                ]);
            }

            $this->fillPerson($person, $contact, $organizationName);
            $this->syncEmail($person, $email);
        }

        $enrichment = PersonEnrichment::query()->updateOrCreate(
            [
                'source' => $source,
                'external_id' => $externalId,
            ],
            [
                'person_id' => $person?->id,
                'payload' => $payload,
                'fetched_at' => Carbon::now(),
            ],
        );

        if ($person !== null)
        {
            ProspectSearchResult::query()
                ->where('source', $source)
                ->where('external_id', $externalId)
                ->whereNull('person_id')
                ->update(['person_id' => $person->id]);
        }

        $searchId = $this->searchIdForTeam($teamId, $source, $externalId, $prospectSearchId);
        $contact->person_id = $person?->id;
        $contact->person_search_id = $searchId;
        $contact->save();

        return $enrichment->person;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillPerson(Person $person, Contact $contact, ?string $organizationName): void
    {
        $organizationName = trim((string) $organizationName);

        $person->fill([
            'name' => $contact->name !== '' ? $contact->name : $person->name,
            'surname' => $contact->surname ?: $person->surname,
            'title' => $contact->title ?: $person->title,
            'role' => $contact->role ?: $person->role,
            'organization_name' => $organizationName !== '' ? $organizationName : $person->organization_name,
            'country' => $contact->country ?: $person->country,
        ]);
        $person->save();
    }

    private function syncEmail(Person $person, string $email): void
    {
        $existing = PersonEmail::query()->where('email', $email)->first();
        if ($existing !== null)
        {
            return;
        }

        $person->emails()->create(['email' => $email]);
    }

    private function findPerson(string $source, string $externalId, ?string $email): ?Person
    {
        $enrichment = PersonEnrichment::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->whereNotNull('person_id')
            ->first();

        if ($enrichment?->person !== null)
        {
            return $enrichment->person;
        }

        if ($email === null)
        {
            return null;
        }

        return PersonEmail::query()->where('email', $email)->first()?->person;
    }

    private function searchIdForTeam(int $teamId, string $source, string $externalId, ?int $requestedSearchId): ?int
    {
        if ($requestedSearchId !== null)
        {
            $owned = ProspectSearch::query()
                ->where('id', $requestedSearchId)
                ->where('team_id', $teamId)
                ->exists();

            if ($owned)
            {
                return $requestedSearchId;
            }
        }

        $result = ProspectSearchResult::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->whereHas('search', function ($query) use ($teamId): void
            {
                $query->where('team_id', $teamId);
            })
            ->latest('id')
            ->first();

        return $result !== null ? (int) $result->person_search_id : null;
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return null;
        }

        return $email;
    }
}
