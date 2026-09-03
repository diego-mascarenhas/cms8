<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerAudienceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string, 3: Category}
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
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $contactsModule = Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            [
                'name' => 'Contacts',
                'icon' => 'users',
                'description' => 'Contacts',
                'is_core' => false,
                'status' => 1,
            ],
        );
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
        $team->enableModule('contacts');

        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $category = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $contactsModule->id,
            'name' => 'Newsletter',
            'color' => '#d4a017',
            'status' => 1,
        ]);

        $sendable = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lucía',
            'surname' => 'García',
            'email' => 'lucia.garcia@cliente.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);
        $sendable->categories()->attach($category->id);

        Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Demo',
            'surname' => 'Fake',
            'email' => 'mailer-demo-'.$team->id.'-demo@fake.com',
            'language' => 'es',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => $leadStatusId,
        ]);

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-audience')->plainTextToken, $category];
    }

    public function test_lists_contacts_with_email_and_marks_demo_as_not_sendable(): void
    {
        [, , $token] = $this->adminWithToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.photo_url', null)
            ->assertJsonPath('data.0.status.label_class', 'bg-label-success')
            ->assertJsonPath('lists.0.color', '#d4a017')
            ->assertJsonPath('status_stats.0.key', 'leads')
            ->assertJsonPath('status_stats.0.count', 2)
            ->assertJsonPath('status_stats.0.percentage', 100)
            ->assertJsonPath('status_stats.0.label_class', 'bg-label-success');

        $rows = collect($response->json('data'));
        $lucia = $rows->firstWhere('email', 'lucia.garcia@cliente.com');
        $this->assertSame('#d4a017', $lucia['categories'][0]['color'] ?? null);
        $this->assertTrue((bool) $lucia['can_send']);
        $fake = $rows->first(fn (array $row): bool => str_ends_with((string) $row['email'], '@fake.com'));
        $this->assertNotNull($fake);
        $this->assertFalse((bool) $fake['can_send']);
    }

    public function test_filters_audience_by_category(): void
    {
        [, , $token, $category] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?category_id='.$category->id)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.email', 'lucia.garcia@cliente.com');
    }

    public function test_creates_audience_contact(): void
    {
        [, $team, $token, $category] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience', [
                'name' => 'Martín',
                'surname' => 'Pérez',
                'email' => 'martin.perez@cliente.com',
                'category_ids' => [$category->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'martin.perez@cliente.com')
            ->assertJsonPath('data.can_send', true)
            ->assertJsonPath('data.categories.0.name', 'Newsletter');

        $this->assertDatabaseHas('contacts', [
            'team_id' => $team->id,
            'email' => 'martin.perez@cliente.com',
            'name' => 'Martín',
        ]);
    }

    public function test_updates_audience_contact_and_categories(): void
    {
        [$user, $team, $token, $category] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');
        $vip = Category::query()->create([
            'team_id' => $team->id,
            'module_id' => $category->module_id,
            'name' => 'VIP',
            'status' => 1,
        ]);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lucia.garcia@cliente.com')
            ->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/'.$contact->id, [
                'name' => 'Lucía',
                'surname' => 'García López',
                'email' => 'lucia.garcia@cliente.com',
                'category_ids' => [$category->id, $vip->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.surname', 'García López')
            ->assertJsonCount(2, 'data.categories');

        $this->assertEqualsCanonicalizing(
            ['Newsletter', 'VIP'],
            collect($response->json('data.categories'))->pluck('name')->all(),
        );

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'surname' => 'García López',
            'creator_id' => $user->id,
        ]);

        $this->assertSame(
            [$category->id, $vip->id],
            $contact->fresh()->categories()->orderBy('categories.id')->pluck('categories.id')->all(),
        );
        $this->assertSame($leadStatusId, (int) $contact->fresh()->status_id);
    }

    public function test_cannot_update_missing_audience_contact(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/audience/999999', [
                'name' => 'Nadie',
                'email' => 'nadie@cliente.com',
            ])
            ->assertNotFound();
    }

    public function test_creates_audience_list_and_reuses_existing_name(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/lists', ['name' => 'Clientes VIP'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Clientes VIP')
            ->assertJsonPath('data.subscribers', 0);

        $this->assertDatabaseHas('categories', [
            'team_id' => $team->id,
            'name' => 'Clientes VIP',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/audience/lists', ['name' => 'clientes vip'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clientes VIP');

        $this->assertSame(1, Category::query()->where('team_id', $team->id)->where('name', 'Clientes VIP')->count());
    }

    public function test_search_matches_name_without_case_or_accents(): void
    {
        [, , $token] = $this->adminWithToken();

        foreach (['lucia', 'LUCÍA', 'garcia'] as $term)
        {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/mailer/audience?search='.rawurlencode($term))
                ->assertOk()
                ->assertJsonPath('pagination.total', 1)
                ->assertJsonPath('data.0.email', 'lucia.garcia@cliente.com');
        }
    }

    public function test_paginates_audience_contacts(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $leadStatusId = (int) ContactStatus::query()->where('name', 'Lead')->value('id');

        for ($i = 1; $i <= 25; $i++)
        {
            Contact::withoutGlobalScopes()->create([
                'team_id' => $team->id,
                'name' => sprintf('Contacto %02d', $i),
                'surname' => 'Extra',
                'email' => "extra{$i}@cliente.com",
                'language' => 'es',
                'country' => 724,
                'creator_id' => $user->id,
                'responsible_id' => $user->id,
                'status_id' => $leadStatusId,
            ]);
        }

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?per_page=20&page=1')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 20)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 27);

        $this->assertCount(20, $first->json('data'));

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/audience?per_page=20&page=2')
            ->assertOk()
            ->assertJsonPath('pagination.current_page', 2);

        $this->assertCount(7, $second->json('data'));
    }

    public function test_guest_cannot_list_audience(): void
    {
        $this->getJson('/api/mailer/audience')->assertUnauthorized();
    }
}
