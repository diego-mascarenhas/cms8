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
            'country' => $this->defaultCountryId(),
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

    private function defaultCountryId(): int
    {
        return (int) (DB::table('countries')->where('id', 724)->value('id')
            ?? DB::table('countries')->value('id')
            ?? 724);
    }

    private function defaultLanguageCode(): string
    {
        return (string) (DB::table('languages')->where('code', 'es')->value('code')
            ?? DB::table('languages')->value('code')
            ?? 'es');
    }
}
