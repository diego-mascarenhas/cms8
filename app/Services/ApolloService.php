<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApolloService
{
    protected string $baseUrl = 'https://api.apollo.io/api/v1';

    protected string $apiKey;

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
                'title' => $person['title'] ?? null,
                'organization_name' => $orgName,
                'organization' => $org,
                'has_email' => $person['has_email'] ?? false,
                'last_refreshed_at' => $person['last_refreshed_at'] ?? null,
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

        if (! $response->successful())
        {
            throw new \RuntimeException(
                'Apollo API error: '.($response->json('error') ?? $response->body()),
                $response->status(),
            );
        }

        $data = $response->json();
        $organizations = $data['organizations'] ?? $data['companies'] ?? [];
        $totalEntries = (int) ($data['total_entries'] ?? 0);

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
            $parts[] = 'q_keywords='.rawurlencode((string) $filters['q_keywords']);
        }

        return implode('&', $parts);
    }
}
