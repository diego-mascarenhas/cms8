<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApolloService
{
    protected string $baseUrl = 'https://api.apollo.io/api/v1';

    protected string $apiKey;

    public ?string $lastPhoneRevealRequestId = null;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.apollo.api_key', '');
    }

    /**
     * Search people in Apollo database by filters.
     *
     * @param  array<string, mixed>  $filters  e.g. person_titles, person_locations, person_seniorities, q_organization_domains_list, organization_num_employees_ranges
     * @return array{people: array<int, array<string, mixed>>, total_entries: int, page: int, per_page: int}
     *
     * @throws \RuntimeException
     */
    public function searchPeople(array $filters, int $page = 1, int $perPage = 25): array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Apollo API key is not configured.');
        }

        $queryString = $this->buildPeopleSearchQueryString($filters, $page, $perPage);
        $url = $this->baseUrl.'/mixed_people/api_search?'.$queryString;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, []);

        $data = $response->json();
        $people = $data['people'] ?? [];
        $totalEntries = (int) ($data['total_entries'] ?? 0);

        if (! $response->successful())
        {
            throw new \RuntimeException(
                'Apollo API error: '.($response->json('error') ?? $response->body()),
                $response->status(),
            );
        }

        $normalized = array_values(array_map(function (array $person): array
        {
            $org = $person['organization'] ?? [];
            $orgName = is_array($org) ? ($org['name'] ?? '') : '';

            return [
                'id' => $person['id'] ?? '',
                'first_name' => $person['first_name'] ?? '',
                'last_name_obfuscated' => $person['last_name_obfuscated'] ?? '',
                'last_name' => $person['last_name'] ?? null,
                'title' => $person['title'] ?? null,
                'organization_name' => $orgName,
                'organization' => $org,
                'has_email' => self::personHasEmail($person),
                'has_phone' => self::personHasPhone($person),
                'last_refreshed_at' => $person['last_refreshed_at'] ?? null,
                'apollo_raw' => $person,
            ];
        }, $people));

        return [
            'people' => $normalized,
            'total_entries' => $totalEntries,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Apollo search hides the address and scatters the phone across flags and nested objects.
     */
    public static function personHasEmail(array $person): bool
    {
        if (self::flagYes($person['has_email'] ?? null))
        {
            return true;
        }

        foreach (['email', 'primary_email', 'sanitized_email'] as $key)
        {
            $value = $person[$key] ?? null;
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL))
            {
                return true;
            }
        }

        return false;
    }

    public static function personHasPhone(array $person): bool
    {
        $sources = [$person];
        foreach (['contact', 'organization'] as $key)
        {
            if (is_array($person[$key] ?? null))
            {
                $sources[] = $person[$key];
            }
        }

        foreach ($sources as $source)
        {
            if (self::sourceHasPhone($source))
            {
                return true;
            }
        }

        return false;
    }

    private static function sourceHasPhone(array $source): bool
    {
        if (self::flagYes($source['has_direct_phone'] ?? null) || self::flagYes($source['has_phone'] ?? null))
        {
            return true;
        }

        foreach (['phone', 'sanitized_phone', 'primary_phone'] as $key)
        {
            $phone = $source[$key] ?? null;
            if (is_string($phone) && trim($phone) !== '')
            {
                return true;
            }
            if (is_array($phone))
            {
                $number = $phone['number'] ?? $phone['sanitized_number'] ?? null;
                if (is_string($number) && trim($number) !== '')
                {
                    return true;
                }
            }
        }

        foreach (['phone_numbers', 'contact_phone_numbers'] as $key)
        {
            if (! empty($source[$key]) && is_array($source[$key]))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Search can flag a direct number without returning it. That number arrives from a later reveal.
     */
    public static function personNeedsPhoneReveal(array $person): bool
    {
        if (self::phoneNumberFromPerson($person) !== null)
        {
            return false;
        }

        if (self::flagYes($person['has_direct_phone'] ?? null) || self::flagYes($person['has_phone'] ?? null))
        {
            return true;
        }

        foreach (['phone_numbers', 'contact_phone_numbers'] as $key)
        {
            if (! empty($person[$key]) && is_array($person[$key]))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    public static function phoneNumberFromPerson(array $person): ?string
    {
        foreach (['sanitized_phone', 'phone'] as $key)
        {
            $value = self::phoneText($person[$key] ?? null);
            if ($value !== null)
            {
                return $value;
            }
        }

        $primary = $person['primary_phone'] ?? null;
        if (is_array($primary))
        {
            $value = self::phoneText($primary['sanitized_number'] ?? $primary['number'] ?? null);
            if ($value !== null)
            {
                return $value;
            }
        }

        foreach (['phone_numbers', 'contact_phone_numbers'] as $key)
        {
            if (! is_array($person[$key] ?? null))
            {
                continue;
            }

            $best = self::bestPhoneNumber($person[$key]);
            if ($best !== null)
            {
                return $best;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $person
     */
    public static function phoneNumberFromOrganization(array $person): ?string
    {
        $organization = $person['organization'] ?? null;

        return is_array($organization) ? self::phoneNumberFromPerson($organization) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function phoneNumberFromRevealPayload(array $payload): ?string
    {
        $people = $payload['webhook_result']['people'] ?? $payload['people'] ?? null;
        if (! is_array($people))
        {
            return null;
        }

        foreach ($people as $person)
        {
            if (! is_array($person))
            {
                continue;
            }

            $phone = self::phoneNumberFromPerson($person);
            if ($phone !== null)
            {
                return $phone;
            }
        }

        return null;
    }

    private static function flagYes(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || (is_string($value) && strcasecmp($value, 'Yes') === 0);
    }

    private static function phoneText(mixed $value): ?string
    {
        if (is_array($value))
        {
            $value = $value['sanitized_number'] ?? $value['raw_number'] ?? $value['number'] ?? null;
        }

        if (! is_string($value))
        {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, mixed>  $numbers
     */
    private static function bestPhoneNumber(array $numbers): ?string
    {
        $fallback = null;
        foreach ($numbers as $number)
        {
            if (is_string($number))
            {
                $text = self::phoneText($number);
            } elseif (is_array($number))
            {
                $text = self::phoneText($number['sanitized_number'] ?? $number['raw_number'] ?? $number['number'] ?? null);
            } else
            {
                $text = null;
            }

            if ($text === null)
            {
                continue;
            }

            $status = is_array($number) ? strtolower((string) ($number['status_cd'] ?? '')) : '';
            $type = is_array($number) ? strtolower((string) ($number['type_cd'] ?? '')) : '';
            if ($status === 'valid_number' && $type === 'mobile')
            {
                return $text;
            }

            $fallback ??= $text;
        }

        return $fallback;
    }

    /**
     * Enrich a person by Apollo id to get full name and email (consumes credits).
     * Calls Apollo People Enrichment API /people/match.
     *
     * @param  array{id: string, first_name?: string, organization_name?: string, organization?: array}  $person  At least id from search; first_name and organization_name improve match.
     * @return array<string, mixed>|null Enriched person array or null if not found/failed
     */
    public function enrichPerson(array $person): ?array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Apollo API key is not configured.');
        }

        $this->lastPhoneRevealRequestId = null;

        $id = $person['id'] ?? '';
        if ($id === '')
        {
            return null;
        }

        $revealPhone = ! empty($person['reveal_phone']);
        $params = [
            'id' => $id,
            'reveal_personal_emails' => 'true',
        ];
        if (! empty($person['first_name']))
        {
            $params['first_name'] = $person['first_name'];
        }
        $org = $person['organization'] ?? [];
        $orgName = is_array($org) ? ($org['name'] ?? $person['organization_name'] ?? '') : ($person['organization_name'] ?? '');
        if ($orgName !== '')
        {
            $params['organization_name'] = $orgName;
        }
        if ($revealPhone)
        {
            $params['reveal_phone_number'] = 'true';
            $params['poll_only'] = 'true';
        }

        $response = $this->postPeopleMatch($params);
        if ($revealPhone && ! $response->successful())
        {
            unset($params['reveal_phone_number'], $params['poll_only']);
            $response = $this->postPeopleMatch($params);
        }

        if (! $response->successful())
        {
            \Log::debug('Apollo enrich failed', [
                'status' => $response->status(),
                'person_id' => $id,
            ]);

            return null;
        }

        $data = $response->json();
        if (! is_array($data))
        {
            return null;
        }

        if ($revealPhone && isset($data['request_id']) && (is_scalar($data['request_id'])))
        {
            $this->lastPhoneRevealRequestId = (string) $data['request_id'];
        }

        $enriched = $data['person'] ?? null;

        return is_array($enriched) ? $enriched : null;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function postPeopleMatch(array $params): \Illuminate\Http\Client\Response
    {
        $url = $this->baseUrl.'/people/match?'.http_build_query($params);

        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, []);
    }

    /**
     * Phone reveal is asynchronous. Apollo returns the number on a later poll.
     *
     * @return array{status: string, phone: ?string, retry_after: int}
     */
    public function pollPhoneReveal(string $requestId): array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Apollo API key is not configured.');
        }

        $url = $this->baseUrl.'/webhook_result/'.rawurlencode($requestId);
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->get($url);

        if ($response->status() === 404 && $response->json('error_code') === 'result_pending')
        {
            return [
                'status' => 'pending',
                'phone' => null,
                'retry_after' => max(5, (int) $response->json('retry_after_seconds', 20)),
            ];
        }

        if (! $response->successful())
        {
            return [
                'status' => 'failed',
                'phone' => null,
                'retry_after' => 0,
            ];
        }

        $payload = $response->json();

        return [
            'status' => 'ready',
            'phone' => is_array($payload) ? self::phoneNumberFromRevealPayload($payload) : null,
            'retry_after' => 0,
        ];
    }

    /**
     * Search organizations (companies) in Apollo database by filters.
     *
     * @param  array<string, mixed>  $filters  e.g. q_organization_domains, organization_locations, organization_num_employees_ranges
     * @return array{organizations: array<int, array<string, mixed>>, total_entries: int, page: int, per_page: int}
     *
     * @throws \RuntimeException
     */
    public function searchOrganizations(array $filters, int $page = 1, int $perPage = 25): array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Apollo API key is not configured.');
        }

        $queryString = $this->buildOrganizationsSearchQueryString($filters, $page, $perPage);
        $url = $this->baseUrl.'/mixed_companies/search?'.$queryString;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, []);

        $data = $response->json();
        $organizations = $data['organizations'] ?? $data['companies'] ?? [];
        $totalEntries = (int) ($data['total_entries'] ?? 0);

        if (! $response->successful())
        {
            throw new \RuntimeException(
                'Apollo API error: '.($response->json('error') ?? $response->body()),
                $response->status(),
            );
        }

        $normalized = array_values(array_map(function (array $org): array
        {
            return [
                'id' => $org['id'] ?? '',
                'name' => $org['name'] ?? '',
                'primary_domain' => $org['primary_domain'] ?? null,
                'website_url' => $org['website_url'] ?? null,
                'estimated_num_employees' => $org['estimated_num_employees'] ?? null,
                'location' => $org['location'] ?? null,
            ];
        }, $organizations));

        return [
            'organizations' => $normalized,
            'total_entries' => $totalEntries,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Build query string for People API so array params are sent as param[]=value (repeated),
     * not param[][0]=value, which Apollo rejects with 422.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildPeopleSearchQueryString(array $filters, int $page, int $perPage): string
    {
        $perPage = min(max(1, $perPage), 100);
        $parts = [
            'page='.(int) $page,
            'per_page='.(int) $perPage,
        ];

        $arrayParams = [
            'person_titles' => 'person_titles[]',
            'person_locations' => 'person_locations[]',
            'person_seniorities' => 'person_seniorities[]',
            'organization_locations' => 'organization_locations[]',
            'q_organization_domains_list' => 'q_organization_domains_list[]',
            'organization_num_employees_ranges' => 'organization_num_employees_ranges[]',
            'organization_ids' => 'organization_ids[]',
        ];

        foreach ($arrayParams as $key => $paramName)
        {
            $value = $filters[$key] ?? null;
            if ($value !== null && $value !== '')
            {
                $arr = is_array($value) ? $value : [$value];
                foreach ($arr as $v)
                {
                    if ((string) $v !== '')
                    {
                        $parts[] = $paramName.'='.rawurlencode((string) $v);
                    }
                }
            }
        }

        if (! empty($filters['q_keywords']))
        {
            $parts[] = 'q_keywords='.rawurlencode((string) $filters['q_keywords']);
        }

        if (isset($filters['include_similar_titles']))
        {
            $parts[] = 'include_similar_titles='.($filters['include_similar_titles'] ? 'true' : 'false');
        }

        return implode('&', $parts);
    }

    /**
     * Build query string for Organizations API with param[]=value (repeated) for array params.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function buildOrganizationsSearchQueryString(array $filters, int $page, int $perPage): string
    {
        $perPage = min(max(1, $perPage), 100);
        $parts = [
            'page='.(int) $page,
            'per_page='.(int) $perPage,
        ];

        if (! empty($filters['q_organization_domains']))
        {
            $domains = is_array($filters['q_organization_domains'])
                ? $filters['q_organization_domains']
                : array_filter(preg_split('/\s*,\s*/', (string) $filters['q_organization_domains']));
            foreach ($domains as $d)
            {
                if ((string) $d !== '')
                {
                    $parts[] = 'q_organization_domains_list[]='.rawurlencode((string) $d);
                }
            }
        }

        $locationKeys = ['organization_locations', 'organization_locations_list'];
        foreach ($locationKeys as $key)
        {
            $value = $filters[$key] ?? null;
            if ($value !== null && $value !== '')
            {
                $arr = is_array($value) ? $value : [$value];
                foreach ($arr as $v)
                {
                    if ((string) $v !== '')
                    {
                        $parts[] = 'organization_locations[]='.rawurlencode((string) $v);
                    }
                }
                break;
            }
        }

        if (! empty($filters['organization_num_employees_ranges']))
        {
            $ranges = is_array($filters['organization_num_employees_ranges'])
                ? $filters['organization_num_employees_ranges']
                : [$filters['organization_num_employees_ranges']];
            foreach ($ranges as $r)
            {
                if ((string) $r !== '')
                {
                    $parts[] = 'organization_num_employees_ranges[]='.rawurlencode((string) $r);
                }
            }
        }

        if (! empty($filters['q_keywords']))
        {
            $keywords = is_array($filters['q_keywords'])
                ? $filters['q_keywords']
                : array_filter(preg_split('/\s*,\s*/', (string) $filters['q_keywords']));
            foreach ($keywords as $kw)
            {
                if ((string) $kw !== '')
                {
                    $parts[] = 'q_organization_keyword_tags[]='.rawurlencode((string) $kw);
                }
            }
        }

        return implode('&', $parts);
    }
}
