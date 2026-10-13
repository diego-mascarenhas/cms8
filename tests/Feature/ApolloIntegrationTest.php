<?php

namespace Tests\Feature;

use App\Jobs\ApplyApolloPhoneRevealJob;
use App\Models\Contact;
use App\Models\Module;
use App\Models\PersonEnrichment;
use App\Models\Team;
use App\Models\User;
use App\Services\ApolloService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApolloIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(LanguageSeeder::class);
        if (DB::table('contact_statuses')->where('id', 1)->doesntExist())
        {
            DB::table('contact_statuses')->insert([
                'id' => 1,
                'name' => 'Lead',
                'label_class' => 'bg-label-success',
            ]);
        }
    }

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

    protected function enableProspectingModule(Team $team): void
    {
        Module::query()->firstOrCreate(
            ['key' => 'prospecting'],
            [
                'name' => 'Prospecting',
                'icon' => 'target',
                'description' => 'Prospect search',
                'is_core' => false,
                'group' => null,
                'order' => 0,
                'status' => 1,
            ],
        );

        $team->enableModule('prospecting');
    }

    public function test_apollo_index_requires_authentication(): void
    {
        $response = $this->get(route('contact.apollo'));

        $response->assertRedirect();
        $this->assertTrue(str_contains($response->headers->get('Location'), 'login'));
    }

    public function test_apollo_index_requires_contact_create_permission(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $user = User::factory()->create();
        $user->teams()->attach($team->id, ['role' => 'guest']);
        $user->current_team_id = $team->id;
        $user->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'guest']));

        // Without the module the route 404s before it ever checks permissions, which would make
        // this test pass for the wrong reason. The guest must not own the team: owners can
        // manage contacts even without a create role.
        $this->enableProspectingModule($team);

        $this->actingAs($user);

        $response = $this->get(route('contact.apollo'));

        $response->assertDeniedForBrowser();
    }

    public function test_apollo_index_returns_404_when_prospecting_module_disabled(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->actingAs($user);

        $response = $this->get(route('contact.apollo'));

        $response->assertStatus(404);
    }

    public function test_apollo_index_returns_view_when_authorized(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $this->assertTrue($user->fresh()->currentTeam->hasModule('prospecting'));
        $this->actingAs($user->fresh());

        $response = $this->get(route('contact.apollo'));

        $response->assertOk();
        $response->assertSee('Encuentra contactos', false);
        $response->assertSee('placeholder="Buenos Aires, Argentina"', false);
        $response->assertSee('id="organization_locations_people" name="organization_locations_people" placeholder="California"', false);
        $response->assertSee('id="apollo_import_category_modal_id"', false);
        $response->assertSee('data-module-key="contacts"', false);
        $response->assertSee('data-dropdown-parent="#apolloImportCategoryModal"', false);
        $response->assertSee('quick-add-module-cat-', false);
        $response->assertDontSee('createTag', false);
    }

    public function test_add_person_as_contact_creates_contact_with_team_and_apollo_data(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-person-123',
            'first_name' => 'Jane',
            'last_name_obfuscated' => 'Do***e',
            'title' => 'VP Sales',
            'organization_name' => 'Acme Inc',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'contact_id', 'redirect_url']);

        $this->assertDatabaseHas('contacts', [
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Jane',
            'surname' => null,
            'status_id' => 1,
        ]);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertSame('VP Sales', $contact->title);
        $this->assertNull($contact->person_id);
        $enrichment = PersonEnrichment::query()->where('external_id', 'apollo-person-123')->first();
        $this->assertNotNull($enrichment);
        $this->assertSame('apollo', $enrichment->source);
        $this->assertSame('Acme Inc', $enrichment->payload['organization_name'] ?? null);
    }

    public function test_add_person_as_contact_redirects_when_not_ajax(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-456',
            'first_name' => 'John',
            'last_name_obfuscated' => '',
            'title' => null,
            'organization_name' => 'Other Co',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/contact/', $response->headers->get('Location'));

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('John', $contact->name);
    }

    public function test_add_person_as_contact_stores_the_person_country_from_apollo(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);
        $this->app->instance(ApolloService::class, new class extends ApolloService
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function enrichPerson(array $person): ?array
            {
                return [
                    'id' => $person['id'] ?? '',
                    'first_name' => 'Jane',
                    'last_name' => 'Doe',
                    'country' => 'United States',
                    'organization' => [
                        'name' => 'Acme Inc',
                        'country' => 'Spain',
                    ],
                ];
            }
        });

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-country-us',
            'first_name' => 'Jane',
            'last_name_obfuscated' => 'Do***e',
            'title' => 'VP Sales',
            'organization_name' => 'Acme Inc',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Jane', $contact->name);
        $this->assertSame('Doe', $contact->surname);
        $this->assertSame(840, (int) $contact->country);
    }

    public function test_add_person_as_contact_uses_the_organization_country_when_the_person_has_none(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);
        $this->app->instance(ApolloService::class, new class extends ApolloService
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function enrichPerson(array $person): ?array
            {
                return [
                    'id' => $person['id'] ?? '',
                    'first_name' => 'Ana',
                    'last_name' => 'Simeone',
                    'organization' => [
                        'name' => 'Acme',
                        'country' => 'Argentina',
                    ],
                ];
            }
        });

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-country-ar',
            'first_name' => 'Ana',
            'last_name_obfuscated' => 'Si***e',
            'organization_name' => 'Acme',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertSame(32, (int) $contact->country);
    }

    public function test_add_person_as_contact_leaves_country_empty_when_apollo_country_is_unknown(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);
        $this->app->instance(ApolloService::class, new class extends ApolloService
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function enrichPerson(array $person): ?array
            {
                return [
                    'id' => $person['id'] ?? '',
                    'first_name' => 'Ana',
                    'last_name' => 'Simeone',
                    'country' => 'Narnia',
                    'organization' => [
                        'name' => 'Acme',
                        'country' => 'Nowhere',
                    ],
                ];
            }
        });

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-country-unknown',
            'first_name' => 'Ana',
            'last_name_obfuscated' => 'Si***e',
            'organization_name' => 'Acme',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->country);
    }

    public function test_add_person_uses_a_personal_email_when_apollo_has_no_work_email(): void
    {
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);
        $this->app->instance(ApolloService::class, new class extends ApolloService
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function enrichPerson(array $person): ?array
            {
                return [
                    'id' => $person['id'] ?? '',
                    'first_name' => 'Carla',
                    'last_name' => 'Martinovic',
                    'email' => null,
                    'personal_emails' => ['carla.martinovic@gmail.com'],
                ];
            }
        });

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => 'apollo-personal-email',
            'first_name' => 'Carla',
            'last_name_obfuscated' => 'Ma***c',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertSame('carla.martinovic@gmail.com', $contact->email);
    }

    public function test_add_person_requests_a_phone_reveal_when_apollo_only_flags_a_direct_number(): void
    {
        Bus::fake();
        $user = $this->createUserWithRole('admin');
        $this->enableProspectingModule($user->currentTeam);
        $user->currentTeam->addProspectCreditsFromPurchase(10);
        $this->actingAs($user);

        $apollo = new class extends ApolloService
        {
            /** @var array<string, mixed> */
            public array $seen = [];

            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function enrichPerson(array $person): ?array
            {
                $this->seen = $person;
                $this->lastPhoneRevealRequestId = ! empty($person['reveal_phone']) ? '1039995589705121900' : null;

                return [
                    'id' => $person['id'] ?? '',
                    'first_name' => 'Ruben',
                    'last_name' => 'Rodriguez',
                    'email' => 'rurodriguez@farmashop.com.uy',
                    'organization' => ['name' => 'Farmashop', 'phone' => null],
                ];
            }
        };
        $this->app->instance(ApolloService::class, $apollo);

        $response = $this->post(route('contact.apollo.add-person'), [
            '_token' => csrf_token(),
            'apollo_id' => '61507be15d7a3800016a9d1d',
            'first_name' => 'Ruben',
            'last_name_obfuscated' => 'Ro***z',
            'organization_name' => 'Farmashop',
            'person_data' => json_encode([
                'id' => '61507be15d7a3800016a9d1d',
                'first_name' => 'Ruben',
                'has_direct_phone' => 'Yes',
                'organization' => ['name' => 'Farmashop', 'phone' => null],
            ]),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $this->assertTrue($apollo->seen['reveal_phone'] ?? false);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $user->currentTeam->id)->latest('id')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Ruben', $contact->name);
        $this->assertSame('Rodriguez', $contact->surname);
        $this->assertNull($contact->phone);

        Bus::assertDispatched(ApplyApolloPhoneRevealJob::class, function (ApplyApolloPhoneRevealJob $job) use ($contact): bool
        {
            return $job->contactId === $contact->id && $job->requestId === '1039995589705121900';
        });
    }

    public function test_phone_reveal_job_saves_the_number_apollo_sends_later(): void
    {
        $user = $this->createUserWithRole('admin');
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $user->currentTeam->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Ruben',
            'surname' => 'Rodriguez',
            'status_id' => 1,
        ]);

        $this->app->instance(ApolloService::class, new class extends ApolloService
        {
            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function pollPhoneReveal(string $requestId): array
            {
                return [
                    'status' => 'ready',
                    'phone' => '+598 99 111 222',
                    'retry_after' => 0,
                ];
            }
        });

        (new ApplyApolloPhoneRevealJob($contact->id, '1039995589705121900'))->handle(app(ApolloService::class));

        $this->assertSame(59899111222, (int) $contact->fresh()->phone);
    }

    public function test_enrich_person_asks_apollo_to_reveal_the_phone_for_a_later_poll(): void
    {
        Http::fake([
            'https://api.apollo.io/api/v1/people/match*' => Http::response([
                'request_id' => 1039995589705121900,
                'person' => ['id' => 'abc', 'first_name' => 'Ruben'],
            ]),
            'https://api.apollo.io/api/v1/webhook_result/*' => Http::response([
                'webhook_result' => [
                    'people' => [[
                        'phone_numbers' => [[
                            'sanitized_number' => '+59899111222',
                            'status_cd' => 'valid_number',
                            'type_cd' => 'mobile',
                        ]],
                    ]],
                ],
            ]),
        ]);

        $service = new ApolloService('test-key');
        $person = $service->enrichPerson([
            'id' => 'abc',
            'reveal_phone' => true,
        ]);

        $this->assertSame('Ruben', $person['first_name'] ?? null);
        $this->assertSame('1039995589705121900', $service->lastPhoneRevealRequestId);
        Http::assertSent(function ($request): bool
        {
            return str_contains($request->url(), 'reveal_phone_number=true')
                && str_contains($request->url(), 'poll_only=true');
        });

        $polled = $service->pollPhoneReveal('1039995589705121900');
        $this->assertSame('ready', $polled['status']);
        $this->assertSame('+59899111222', $polled['phone']);
    }
}
