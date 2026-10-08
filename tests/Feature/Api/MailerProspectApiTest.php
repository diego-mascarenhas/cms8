<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\Module;
use App\Models\ProspectUsageLog;
use App\Models\User;
use App\Services\ApolloService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerProspectApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_prospecting_stays_disabled_during_the_plan_trial(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $fake = $this->bindApollo();

        $this->withToken($token)
            ->getJson('/api/mailer/prospects')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('reason', 'La prospección se habilita al contratar el plan.');

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/search', [
                'person_titles' => ['director comercial'],
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/import', [
                'people' => [[
                    'id' => 'p1',
                    'first_name' => 'Ana',
                    'apollo_raw' => ['seniority' => 'owner'],
                ]],
            ])
            ->assertForbidden();

        $this->assertSame(0, $fake->searches);
        $this->assertSame(0, Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('email', 'ana@acme.test')->count());
        $this->assertSame(0, ProspectUsageLog::query()->where('team_id', $team->id)->count());
    }

    public function test_a_whitelisted_team_can_search_and_import_spends_credits(): void
    {
        [, $team, $token] = $this->adminWithToken();
        config(['humano_pricing.plan_access_team_ids' => [$team->id]]);
        $team->addProspectCreditsFromPurchase(5);
        $fake = $this->bindApollo();

        $this->withToken($token)
            ->getJson('/api/mailer/prospects')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('credits', 5);

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/search', [
                'person_titles' => ['owner'],
            ])
            ->assertOk()
            ->assertJsonPath('people.0.id', 'p1')
            ->assertJsonPath('people.0.credits', 3)
            ->assertJsonPath('credits', 5);

        $this->assertSame(1, $fake->searches);
        $this->assertSame(5, $team->fresh()->getRemainingProspectCredits());

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/import', [
                'people' => [
                    [
                        'id' => 'p1',
                        'first_name' => 'Ana',
                        'organization_name' => 'Acme',
                        'apollo_raw' => ['id' => 'p1', 'seniority' => 'owner', 'organization' => ['name' => 'Acme']],
                    ],
                    [
                        'id' => 'p2',
                        'first_name' => 'Luis',
                        'apollo_raw' => ['id' => 'p2', 'seniority' => 'owner'],
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('imported.0.email', 'ana@acme.test')
            ->assertJsonPath('imported.1.email', 'luis@acme.test')
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('credits', 0);

        $contact = Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('email', 'ana@acme.test')->first();
        $this->assertNotNull($contact);
        $this->assertSame('Ana Simeone', $contact->name);
        $this->assertNotNull(Contact::withoutGlobalScopes()->where('team_id', $team->id)->where('email', 'luis@acme.test')->first());
        $this->assertSame(6, (int) ProspectUsageLog::query()->where('team_id', $team->id)->sum('count'));
        $this->assertSame(0, $team->fresh()->getRemainingProspectCredits());
    }

    public function test_a_paid_mailer_team_can_search_without_the_whitelist(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $team->addMailerCreditsFromPurchase(1);
        $this->bindApollo();

        $this->withToken($token)
            ->getJson('/api/mailer/prospects')
            ->assertOk()
            ->assertJsonPath('enabled', true);

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/search', [
                'q_keywords' => 'software',
            ])
            ->assertOk()
            ->assertJsonPath('people.0.first_name', 'Ana');
    }

    public function test_import_without_credits_is_recorded_as_usage(): void
    {
        [, $team, $token] = $this->adminWithToken();
        config(['humano_pricing.plan_access_team_ids' => [$team->id]]);
        $this->bindApollo();

        $this->withToken($token)
            ->postJson('/api/mailer/prospects/import', [
                'people' => [[
                    'id' => 'p1',
                    'first_name' => 'Ana',
                    'apollo_raw' => ['seniority' => 'director'],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('imported.0.email', 'ana@acme.test')
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('credits', 0);

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertSame(1, (int) ProspectUsageLog::query()->where('team_id', $team->id)->sum('count'));
        $this->assertSame(0, $team->fresh()->getRemainingProspectCredits());
    }

    private function bindApollo(): ApolloService
    {
        $fake = new class extends ApolloService
        {
            public int $searches = 0;

            public function __construct()
            {
                parent::__construct('test-key');
            }

            public function searchPeople(array $filters, int $page = 1, int $perPage = 25): array
            {
                $this->searches++;

                return [
                    'people' => [[
                        'id' => 'p1',
                        'first_name' => 'Ana',
                        'last_name_obfuscated' => 'S***',
                        'title' => 'Owner',
                        'organization_name' => 'Acme',
                        'has_email' => true,
                        'has_phone' => false,
                        'apollo_raw' => [
                            'id' => 'p1',
                            'first_name' => 'Ana',
                            'seniority' => 'owner',
                        ],
                    ]],
                    'total_entries' => 1,
                    'page' => $page,
                    'per_page' => $perPage,
                ];
            }

            public function enrichPerson(array $person): ?array
            {
                $id = (string) ($person['id'] ?? '');

                return [
                    'id' => $id,
                    'first_name' => $id === 'p2' ? 'Luis' : 'Ana',
                    'last_name' => $id === 'p2' ? 'Perez' : 'Simeone',
                    'email' => $id === 'p2' ? 'luis@acme.test' : 'ana@acme.test',
                    'seniority' => 'owner',
                    'organization' => ['name' => 'Acme'],
                ];
            }
        };

        $this->app->instance(ApolloService::class, $fake);

        return $fake;
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            EnterpriseTypeSeeder::class,
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'mailer'],
            [
                'name' => 'Mailer',
                'icon' => 'mail',
                'description' => 'Mailer',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('mailer');

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-prospects')->plainTextToken];
    }
}
