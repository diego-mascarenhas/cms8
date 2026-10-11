<?php

namespace App\Services\Prospecting;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use App\Services\ApolloService;
use Illuminate\Support\Facades\DB;

class ProspectContactImporter
{
    /**
     * @var array<string, int>|null
     */
    private ?array $countryIdsByLabel = null;

    public function __construct(private ApolloService $apollo) {}

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

        $enriched = null;
        try
        {
            $enriched = $this->apollo->enrichPerson([
                'id' => $apolloId,
                'first_name' => $firstName,
                'organization_name' => $apolloData['organization_name'] ?? $organizationName,
                'organization' => $apolloData['organization'] ?? null,
            ]);
        } catch (\Throwable)
        {
            $enriched = null;
        }

        if (is_array($enriched) && $enriched !== [])
        {
            $apolloData = array_merge($enriched, ['apollo_id' => $enriched['id'] ?? $apolloId]);
            $name = trim(($enriched['first_name'] ?? '').' '.($enriched['last_name'] ?? ''));
            if ($name === '')
            {
                $name = $enriched['name'] ?? trim($firstName.' '.($lastNameObfuscated ?? '')) ?: 'Contact';
            }
        } else
        {
            $fallbackLast = $lastName ?? $lastNameObfuscated ?? '';
            $name = trim($firstName.' '.$fallbackLast) ?: 'Contact';
        }

        $dataJson = ['apollo' => $apolloData];
        $dataJson['apollo_raw'] = (is_array($enriched) && $enriched !== []) ? $enriched : $apolloData;

        $contactData = [
            'team_id' => $team->id,
            'creator_id' => $userId,
            'responsible_id' => $userId,
            'name' => $name ?: 'Contact',
            'status_id' => (int) (ContactStatus::query()->where('name', 'Lead')->value('id') ?? 1),
            'country' => $this->countryIdFromApollo($apolloData),
            'language' => $this->defaultLanguageCode(),
            'data' => $dataJson,
        ];

        $rawEmail = $apolloData['email'] ?? $apolloData['primary_email'] ?? $apolloData['sanitized_email'] ?? '';
        if ((string) $rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL))
        {
            $contactData['email'] = strtolower(trim((string) $rawEmail));
        }

        $phoneStr = $this->phoneString($apolloData);
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

        return $contact;
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
     * @param  array<string, mixed>  $apolloData
     */
    private function phoneString(array $apolloData): ?string
    {
        $phone = $apolloData['phone'] ?? null;
        if (is_string($phone) && $phone !== '')
        {
            return $phone;
        }
        if (is_array($phone) && isset($phone['number']) && is_string($phone['number']))
        {
            return $phone['number'];
        }
        if (! empty($apolloData['phone_numbers']) && is_array($apolloData['phone_numbers']))
        {
            $first = reset($apolloData['phone_numbers']);
            if (is_string($first))
            {
                return $first;
            }
            if (is_array($first) && isset($first['number']) && is_string($first['number']))
            {
                return $first['number'];
            }
        }

        return null;
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
