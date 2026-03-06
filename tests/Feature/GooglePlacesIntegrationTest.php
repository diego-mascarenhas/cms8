<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GooglePlacesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $user->teams()->attach($team->id, ['role' => $roleName]);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole($role);

        return $user->refresh();
    }

    public function test_places_search_requires_authentication(): void
    {
        $response = $this->getJson(route('places.search', ['text_query' => 'restaurant']));

        $response->assertStatus(401);
    }

    public function test_places_search_requires_authorization(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Http::fake([
            'places.googleapis.com/*' => Http::response(['places' => []], 200),
        ]);

        $response = $this->getJson(route('places.search', ['text_query' => 'restaurant Madrid']));

        $response->assertStatus(403);
    }

    public function test_places_search_returns_places_when_authorized(): void
    {
        $this->app['config']->set('services.google.places_api_key', 'test-key');
        $user = $this->createUserWithRole('admin');

        Http::fake([
            'places.googleapis.com/v1/places:searchText' => Http::response([
                'places' => [
                    [
                        'name' => 'places/ChIJtest123',
                        'displayName' => ['text' => 'Test Restaurant', 'languageCode' => 'en'],
                        'formattedAddress' => 'Calle Test 1, Madrid, Spain',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('places.search', ['text_query' => 'restaurant Madrid']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['places' => [['id', 'name', 'formatted_address']]]);
        $response->assertJsonPath('places.0.id', 'ChIJtest123');
        $response->assertJsonPath('places.0.name', 'Test Restaurant');
        $response->assertJsonPath('places.0.formatted_address', 'Calle Test 1, Madrid, Spain');
    }

    public function test_places_details_returns_place_for_enterprise_prefill(): void
    {
        $this->app['config']->set('services.google.places_api_key', 'test-key');
        $user = $this->createUserWithRole('admin');

        Http::fake([
            'places.googleapis.com/v1/places/ChIJtest123' => Http::response([
                'displayName' => ['text' => 'Test Business', 'languageCode' => 'en'],
                'formattedAddress' => 'Calle Example 5, 28001 Madrid, Spain',
                'addressComponents' => [
                    ['types' => ['street_number'], 'longText' => '5', 'shortText' => '5'],
                    ['types' => ['route'], 'longText' => 'Calle Example', 'shortText' => 'Calle Example'],
                    ['types' => ['locality'], 'longText' => 'Madrid', 'shortText' => 'Madrid'],
                    ['types' => ['administrative_area_level_1'], 'longText' => 'Madrid', 'shortText' => 'M'],
                    ['types' => ['postal_code'], 'longText' => '28001', 'shortText' => '28001'],
                    ['types' => ['country'], 'longText' => 'Spain', 'shortText' => 'ES'],
                ],
                'nationalPhoneNumber' => '+34 912 345 678',
                'websiteUri' => 'https://example.com',
            ], 200),
        ]);

        $this->actingAs($user);

        $response = $this->getJson(route('places.details', ['placeId' => 'ChIJtest123']));

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Business');
        $response->assertJsonPath('phone', '+34 912 345 678');
        $response->assertJsonPath('website', 'https://example.com');
        $response->assertJsonPath('locality', 'Madrid');
        $response->assertJsonPath('country', 'Spain');
        $response->assertJsonPath('postal_code', '28001');
        $response->assertJsonStructure(['email', 'opening_hours', 'latitude', 'longitude', 'api_response']);
    }

    public function test_places_use_for_client_redirects_to_client_create_with_place_data(): void
    {
        $this->app['config']->set('services.google.places_api_key', 'test-key');
        $user = $this->createUserWithRole('admin');

        Http::fake([
            'places.googleapis.com/v1/places/ChIJuse123' => Http::response([
                'displayName' => ['text' => 'Client From Places', 'languageCode' => 'en'],
                'formattedAddress' => 'Av Place 10, Barcelona',
                'addressComponents' => [
                    ['types' => ['locality'], 'longText' => 'Barcelona', 'shortText' => 'BCN'],
                    ['types' => ['country'], 'longText' => 'Spain', 'shortText' => 'ES'],
                ],
                'nationalPhoneNumber' => '+34 933 000 000',
                'websiteUri' => 'https://client.example',
            ], 200),
        ]);

        $this->actingAs($user);

        $response = $this->post(route('places.use-for-client'), [
            'place_id' => 'ChIJuse123',
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect(route('client.create'));
        $this->assertNotNull(session('place_data'));
        $this->assertEquals('Client From Places', session('place_data')['name']);
        $this->assertEquals('Barcelona', session('place_data')['locality']);
    }
}
