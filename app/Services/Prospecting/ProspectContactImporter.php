<?php

namespace App\Services\Prospecting;

use App\Jobs\ApplyApolloPhoneRevealJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use App\Services\ApolloService;
use App\Services\Contacts\TeamContactMatcher;
use Illuminate\Support\Facades\DB;

class ProspectContactImporter
{
    /**
     * @var array<string, int>|null
     */
    private ?array $countryIdsByLabel = null;

    public function __construct(
        private ApolloService $apollo,
        private ProspectDirectory $directory,
    ) {}

    /**
     * @param  array<string, mixed>  $apolloData
     */
    public function creditsCost(array $apolloData): int
    {
        $credits = config('prospects.credits_per_position', []);

        return (int) ($credits[$this->normalizePosition($apolloData)] ?? config('prospects.default_credits', 1));
    }

    /**
     * Record the import as prospect consumption, enrich the person and store them as a contact.
     *
     * @param  array<string, mixed>|null  $person
     */
    public function import(
        Team $team,
        int $userId,
        string $apolloId,
        string $firstName,
        ?string $lastName,
        ?string $lastNameObfuscated,
        ?string $title,
        ?string $organizationName,
        ?array $person,
        ?int $categoryId,
        ?int $prospectSearchId = null,
    ): Contact {
        $apolloData = [
            'apollo_id' => $apolloId,
            'title' => $title,
            'organization_name' => $organizationName,
        ];
        if (is_array($person) && $person !== [])
        {
            $apolloData = $person;
        }

        $team->decrementProspectCredits($this->creditsCost($apolloData));

        $revealPhone = is_array($person) && ApolloService::personNeedsPhoneReveal($person);
        $cached = $this->directory->cachedPayload(ProspectDirectory::SOURCE_APOLLO, $apolloId);
        $enriched = null;
        if (is_array($cached))
        {
            $enriched = $cached;
        } else
        {
            try
            {
                $enrichQuery = [
                    'id' => $apolloId,
                    'first_name' => $firstName,
                    'organization_name' => $apolloData['organization_name'] ?? $organizationName,
                    'organization' => $apolloData['organization'] ?? null,
                ];
                if ($revealPhone)
                {
                    $enrichQuery['reveal_phone'] = true;
                }
                $enriched = $this->apollo->enrichPerson($enrichQuery);
            } catch (\Throwable)
            {
                $enriched = null;
            }
        }

        $nameSource = [];
        if (is_array($enriched) && $enriched !== [])
        {
            $apolloData = array_merge($enriched, ['apollo_id' => $enriched['id'] ?? $apolloId]);
            $nameSource = $enriched;
        } elseif (is_array($person))
        {
            $nameSource = $person;
        }

        [$name, $surname] = $this->contactNameParts($nameSource, $firstName, $lastName, $lastNameObfuscated);

        $storedTitle = trim((string) ($apolloData['title'] ?? $title ?? ''));

        $contactData = [
            'team_id' => $team->id,
            'creator_id' => $userId,
            'responsible_id' => $userId,
            'name' => $name,
            'surname' => $surname,
            'title' => $storedTitle !== '' ? mb_substr($storedTitle, 0, 255) : null,
            'status_id' => (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1),
            'country' => $this->countryIdFromApollo($apolloData),
            'language' => $this->defaultLanguageCode(),
        ];

        $email = $this->emailFromApollo($apolloData);
        if ($email !== null)
        {
            $contactData['email'] = $email;
        }

        $phoneStr = ApolloService::phoneNumberFromPerson($apolloData);
        $phoneRevealRequestId = $this->apollo->lastPhoneRevealRequestId;
        if ($phoneStr === null && ($phoneRevealRequestId === null || $phoneRevealRequestId === ''))
        {
            $phoneStr = ApolloService::phoneNumberFromOrganization($apolloData);
            if ($phoneStr === null && is_array($person))
            {
                $phoneStr = ApolloService::phoneNumberFromPerson($person)
                    ?? ApolloService::phoneNumberFromOrganization($person);
            }
        }
        if ($phoneStr !== null)
        {
            $phoneDigits = preg_replace('/\D/', '', $phoneStr);
            if (is_string($phoneDigits) && $phoneDigits !== '')
            {
                $contactData['phone'] = (int) $phoneDigits;
            }
        }

        $contact = Contact::withoutGlobalScopes()->create($contactData);
        $contact->categories()->sync($this->categoryIds($team, $categoryId));

        $storedOrganization = trim((string) ($organizationName ?? ($apolloData['organization_name'] ?? '')));
        if ($storedOrganization === '' && is_array($apolloData['organization'] ?? null))
        {
            $storedOrganization = trim((string) ($apolloData['organization']['name'] ?? ''));
        }

        $this->directory->storeImportedContact(
            $contact,
            (int) $team->id,
            ProspectDirectory::SOURCE_APOLLO,
            $apolloId,
            $apolloData,
            $storedOrganization !== '' ? $storedOrganization : null,
            $prospectSearchId,
        );

        if (
            ($phoneRevealRequestId !== null && $phoneRevealRequestId !== '')
            && empty($contactData['phone'])
        ) {
            ApplyApolloPhoneRevealJob::dispatch($contact->id, $phoneRevealRequestId)
                ->delay(now()->addSeconds(15));
        }

        return $contact;
    }

    /**
     * Apollo sends first_name and last_name separately. A masked last name stays out of surname.
     *
     * @param  array<string, mixed>  $source
     * @return array{0: string, 1: ?string}
     */
    private function contactNameParts(array $source, string $firstName, ?string $lastName, ?string $lastNameObfuscated): array
    {
        $first = $this->cleanNamePart($source['first_name'] ?? null);
        if ($first === '')
        {
            $first = $this->cleanNamePart($firstName);
        }

        $surname = $this->realLastName($source['last_name'] ?? null)
            ?? $this->realLastName($lastName)
            ?? $this->realLastName($source['last_name_obfuscated'] ?? null)
            ?? $this->realLastName($lastNameObfuscated);

        if ($first === '' || $surname === null)
        {
            $full = $this->cleanNamePart($source['name'] ?? null);
            if ($full !== '' && ! str_contains($full, '*'))
            {
                [$splitFirst, $splitLast] = app(TeamContactMatcher::class)->splitFullName($full);
                if ($first === '')
                {
                    $first = $splitFirst;
                }
                if ($surname === null)
                {
                    $surname = $splitLast;
                }
            }
        }

        if ($first === '')
        {
            $first = 'Contact';
        }

        return [$first, $surname];
    }

    private function cleanNamePart(mixed $value): string
    {
        if (! is_string($value))
        {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function realLastName(mixed $value): ?string
    {
        $clean = $this->cleanNamePart($value);
        if ($clean === '' || str_contains($clean, '*'))
        {
            return null;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $apolloData
     */
    private function normalizePosition(array $apolloData): string
    {
        $raw = $apolloData['apollo_raw'] ?? $apolloData;
        if (! is_array($raw))
        {
            $raw = $apolloData;
        }

        $seniority = $raw['seniority'] ?? $raw['person_seniority'] ?? null;
        if (! is_string($seniority) || $seniority === '')
        {
            return 'manager';
        }

        $key = strtolower(trim((string) preg_replace('/[^a-z0-9_]/', '_', $seniority)));
        $key = str_replace('__', '_', $key);
        $allowed = array_keys(config('prospects.credits_per_position', []));

        return in_array($key, $allowed, true) ? $key : 'manager';
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(Team $team, ?int $categoryId): array
    {
        if ($categoryId === null)
        {
            return [];
        }

        $contactsModule = Module::query()->where('key', 'contacts')->first();
        $category = Category::query()
            ->where('id', $categoryId)
            ->where('status', 1)
            ->when($contactsModule, fn ($query) => $query->where('module_id', $contactsModule->id))
            ->where(fn ($query) => $query->whereNull('team_id')->orWhere('team_id', $team->id))
            ->first();

        return $category ? [$category->id] : [];
    }

    /**
     * @param  array<string, mixed>  $apolloData
     */
    /**
     * Work email first. A personal address is used only when Apollo did not return a usable primary email.
     *
     * @param  array<string, mixed>  $apolloData
     */
    private function emailFromApollo(array $apolloData): ?string
    {
        foreach (['email', 'primary_email', 'sanitized_email'] as $key)
        {
            $email = $this->usableEmail($apolloData[$key] ?? null);
            if ($email !== null)
            {
                return $email;
            }
        }

        $personal = $apolloData['personal_emails'] ?? [];
        if (! is_array($personal))
        {
            return null;
        }

        foreach ($personal as $entry)
        {
            $candidate = is_array($entry)
                ? ($entry['email'] ?? $entry['sanitized_email'] ?? null)
                : $entry;
            $email = $this->usableEmail($candidate);
            if ($email !== null)
            {
                return $email;
            }
        }

        return null;
    }

    private function usableEmail(mixed $value): ?string
    {
        if (! is_string($value))
        {
            return null;
        }

        $email = strtolower(trim($value));
        if ($email === '' || str_contains($email, '*') || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return null;
        }

        return $email;
    }

    private function countryIdFromApollo(array $apolloData): ?int
    {
        foreach ($this->countryCandidates($apolloData) as $candidate)
        {
            $id = $this->countryIdForLabel($candidate);
            if ($id !== null)
            {
                return $id;
            }
        }

        return null;
    }

    /**
     * Person country first, then the current employer's headquarters.
     *
     * @param  array<string, mixed>  $apolloData
     * @return list<string>
     */
    private function countryCandidates(array $apolloData): array
    {
        $candidates = [];
        foreach (['country', 'country_code'] as $key)
        {
            $value = $apolloData[$key] ?? null;
            if (is_string($value) && trim($value) !== '')
            {
                $candidates[] = trim($value);
            }
        }

        $organization = $apolloData['organization'] ?? null;
        if (is_array($organization))
        {
            foreach (['country', 'country_code'] as $key)
            {
                $value = $organization[$key] ?? null;
                if (is_string($value) && trim($value) !== '')
                {
                    $candidates[] = trim($value);
                }
            }
        }

        return $candidates;
    }

    private function countryIdForLabel(string $label): ?int
    {
        $normalized = mb_strtolower(trim($label));
        if ($normalized === '')
        {
            return null;
        }

        $index = $this->countryIdsByLabel();
        if (isset($index[$normalized]))
        {
            return $index[$normalized];
        }

        $alias = $this->countryAliases()[$normalized] ?? null;
        if ($alias !== null && isset($index[$alias]))
        {
            return $index[$alias];
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function countryIdsByLabel(): array
    {
        if ($this->countryIdsByLabel !== null)
        {
            return $this->countryIdsByLabel;
        }

        $index = [];
        $countries = DB::table('countries')->get(['id', 'name', 'code']);
        foreach ($countries as $country)
        {
            $id = (int) $country->id;
            $code = strtolower((string) $country->code);
            $index[$code] = $id;
            $index[(string) $id] = $id;
            $name = mb_strtolower(trim((string) $country->name));
            if ($name !== '')
            {
                $index[$name] = $id;
            }

            if ($code !== '' && class_exists(\Locale::class))
            {
                $english = \Locale::getDisplayRegion('und_'.strtoupper($code), 'en');
                if (is_string($english) && $english !== '')
                {
                    $index[mb_strtolower($english)] = $id;
                }
            }
        }

        $this->countryIdsByLabel = $index;

        return $index;
    }

    /**
     * Apollo names that ICU does not emit for the stored ISO code.
     *
     * @return array<string, string>
     */
    private function countryAliases(): array
    {
        return [
            'usa' => 'us',
            'u.s.' => 'us',
            'u.s.a.' => 'us',
            'united states of america' => 'us',
            'uk' => 'gb',
            'great britain' => 'gb',
            'england' => 'gb',
            'czech republic' => 'cz',
            'turkey' => 'tr',
            'holland' => 'nl',
            'the netherlands' => 'nl',
        ];
    }

    private function defaultLanguageCode(): string
    {
        return (string) (DB::table('languages')->where('code', 'es')->value('code')
            ?? DB::table('languages')->value('code')
            ?? 'es');
    }
}
