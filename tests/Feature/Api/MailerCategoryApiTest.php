<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MailerCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_categories_require_authentication(): void
    {
        $this->getJson('/api/mailer/categories')->assertStatus(401);
        $this->postJson('/api/mailer/categories', ['name' => 'Alfa'])->assertStatus(401);
        $this->patchJson('/api/mailer/categories/sort', ['sort' => 'manual'])->assertStatus(401);
        $this->putJson('/api/mailer/categories/order', ['ids' => [1]])->assertStatus(401);
    }

    public function test_index_lists_team_contact_categories_with_color(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $this->contactsCategory('Beta', $team, '#6B8CAE');
        $this->contactsCategory('Alfa', $team, '#C4A574');
        $this->contactsCategory('Ajena', Team::factory()->create(), '#DC4F5F');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/categories');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.sort', 'name');
        $this->assertSame(['Alfa', 'Beta'], collect($response->json('data.categories'))->pluck('name')->all());
        $response->assertJsonPath('data.categories.0.color', '#c4a574');
        $response->assertJsonPath('data.categories.0.contacts_count', 0);
    }

    public function test_store_creates_a_colored_category(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/categories', [
                'name' => '  Mayorista  ',
                'color' => '#D4A017',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mayorista')
            ->assertJsonPath('data.color', '#d4a017')
            ->assertJsonPath('data.contacts_count', 0);

        $this->assertDatabaseHas('categories', [
            'name' => 'Mayorista',
            'team_id' => $team->id,
            'module_id' => $this->contactsModuleId(),
            'color' => '#d4a017',
            'status' => 1,
        ]);
    }

    public function test_store_rejects_a_duplicate_name_and_an_invalid_color(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $this->contactsCategory('Mayorista', $team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/categories', [
                'name' => 'mayorista',
                'color' => '#c4a574',
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mailer/categories', [
                'name' => 'Retail',
                'color' => 'gold',
            ])
            ->assertStatus(422);
    }

    public function test_update_renames_and_changes_color(): void
    {
        [$user, $team, $token] = $this->adminWithToken();
        $category = $this->contactsCategory('Vieja', $team, '#6b8cae');
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'name' => 'Ana',
            'email' => 'ana@cliente.com',
            'status_id' => (int) ContactStatus::query()->where('name', 'Lead')->value('id'),
        ]);
        $contact->categories()->sync([$category->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/mailer/categories/'.$category->id, [
                'name' => 'Nueva',
                'color' => '#DC4F5F',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nueva')
            ->assertJsonPath('data.color', '#dc4f5f')
            ->assertJsonPath('data.contacts_count', 1);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Nueva',
            'color' => '#dc4f5f',
        ]);
    }

    public function test_cannot_update_or_delete_another_teams_category(): void
    {
        [, , $token] = $this->adminWithToken();
        $foreign = $this->contactsCategory('Ajena', Team::factory()->create(), '#dc4f5f');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/mailer/categories/'.$foreign->id, [
                'name' => 'Hack',
            ])
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/mailer/categories/'.$foreign->id)
            ->assertNotFound();

        $this->assertDatabaseHas('categories', [
            'id' => $foreign->id,
            'name' => 'Ajena',
            'deleted_at' => null,
        ]);
    }

    public function test_destroy_soft_deletes_a_team_category(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $category = $this->contactsCategory('Alfa', $team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/mailer/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mailer/categories')
            ->assertOk()
            ->assertJsonPath('data.categories', []);
    }

    public function test_sort_can_switch_to_manual_and_reorder_persists(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $beta = $this->contactsCategory('Beta', $team);
        $alfa = $this->contactsCategory('Alfa', $team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/mailer/categories/sort', ['sort' => 'manual'])
            ->assertOk()
            ->assertJsonPath('data.sort', 'manual');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/categories/order', ['ids' => [$beta->id, $alfa->id]])
            ->assertOk()
            ->assertJsonPath('data.sort', 'manual')
            ->assertJsonPath('data.categories.0.name', 'Beta')
            ->assertJsonPath('data.categories.1.name', 'Alfa');
    }

    public function test_reorder_rejects_foreign_or_incomplete_ids(): void
    {
        [, $team, $token] = $this->adminWithToken();
        $alfa = $this->contactsCategory('Alfa', $team);
        $this->contactsCategory('Beta', $team);
        $foreign = $this->contactsCategory('Ajena', Team::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/mailer/categories/sort', ['sort' => 'manual'])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/categories/order', ['ids' => [$alfa->id, $foreign->id]])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/mailer/categories/order', ['ids' => [$alfa->id]])
            ->assertStatus(422);
    }

    /**
     * @return array{0: User, 1: Team, 2: string}
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

        Module::query()->firstOrCreate(
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

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-categories')->plainTextToken];
    }

    private function contactsCategory(string $name, Team $team, ?string $color = null): Category
    {
        return Category::query()->create([
            'name' => $name,
            'module_id' => $this->contactsModuleId(),
            'team_id' => $team->id,
            'status' => 1,
            'color' => $color,
        ]);
    }

    private function contactsModuleId(): int
    {
        return (int) Module::query()->where('key', 'contacts')->value('id');
    }
}
