<?php

namespace Tests\Feature;

use App\Services\ApolloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApolloServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('services.apollo.api_key', 'test-apollo-key');
    }

    public function test_search_people_throws_when_api_key_not_configured(): void
    {
        $this->app['config']->set('services.apollo.api_key', '');
        $service = new ApolloService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apollo API key is not configured');

        $service->searchPeople([], 1, 25);
    }

    public function test_search_people_returns_normalized_results(): void
    {
        Http::fake([
            'api.apollo.io/*' => Http::response([
                'people' => [
                    [
                        'id' => 'person-123',
                        'first_name' => 'Jane',
                        'last_name_obfuscated' => 'Do***e',
                        'title' => 'VP Sales',
                        'organization' => ['name' => 'Acme Inc'],
                        'has_email' => true,
                        'last_refreshed_at' => '2025-01-01T00:00:00.000Z',
                    ],
                ],
                'total_entries' => 1,
            ], 200),
        ]);

        $service = new ApolloService;
        $result = $service->searchPeople(['person_titles' => ['sales']], 1, 25);

        $this->assertArrayHasKey('people', $result);
        $this->assertArrayHasKey('total_entries', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertSame(1, $result['total_entries']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(25, $result['per_page']);
        $this->assertCount(1, $result['people']);
        $this->assertSame('person-123', $result['people'][0]['id']);
        $this->assertSame('Jane', $result['people'][0]['first_name']);
        $this->assertSame('Do***e', $result['people'][0]['last_name_obfuscated']);
        $this->assertSame('VP Sales', $result['people'][0]['title']);
        $this->assertSame('Acme Inc', $result['people'][0]['organization_name']);
    }

    public function test_search_people_throws_on_api_error(): void
    {
        Http::fake([
            'api.apollo.io/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = new ApolloService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apollo API error');

        $service->searchPeople([], 1, 25);
    }

    public function test_search_organizations_throws_when_api_key_not_configured(): void
    {
        $this->app['config']->set('services.apollo.api_key', '');
        $service = new ApolloService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Apollo API key is not configured');

        $service->searchOrganizations([], 1, 25);
    }

    public function test_search_organizations_returns_normalized_results(): void
    {
        Http::fake([
            'api.apollo.io/*' => Http::response([
                'organizations' => [
                    [
                        'id' => 'org-456',
                        'name' => 'Acme Corp',
                        'primary_domain' => 'acme.com',
                        'website_url' => 'https://acme.com',
                        'estimated_num_employees' => 150,
                        'location' => 'New York, NY',
                    ],
                ],
                'total_entries' => 1,
            ], 200),
        ]);

        $service = new ApolloService;
        $result = $service->searchOrganizations(['q_organization_domains' => 'acme.com'], 1, 25);

        $this->assertArrayHasKey('organizations', $result);
        $this->assertArrayHasKey('total_entries', $result);
        $this->assertSame(1, $result['total_entries']);
        $this->assertCount(1, $result['organizations']);
        $this->assertSame('org-456', $result['organizations'][0]['id']);
        $this->assertSame('Acme Corp', $result['organizations'][0]['name']);
        $this->assertSame('acme.com', $result['organizations'][0]['primary_domain']);
    }

    public function test_search_organizations_accepts_companies_key_in_response(): void
    {
        Http::fake([
            'api.apollo.io/*' => Http::response([
                'companies' => [
                    [
                        'id' => 'co-789',
                        'name' => 'Other Co',
                        'primary_domain' => null,
                        'website_url' => null,
                        'estimated_num_employees' => null,
                        'location' => null,
                    ],
                ],
                'total_entries' => 1,
            ], 200),
        ]);

        $service = new ApolloService;
        $result = $service->searchOrganizations([], 1, 10);

        $this->assertCount(1, $result['organizations']);
        $this->assertSame('co-789', $result['organizations'][0]['id']);
        $this->assertSame('Other Co', $result['organizations'][0]['name']);
    }
}
