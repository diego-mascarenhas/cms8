<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppThreadCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111222';

    public function test_thread_exposes_the_contact_categories(): void
    {
        [$token, , $team] = $this->inbox();
        $shared = $this->contactsCategory('Mayorista', null);
        $own = $this->contactsCategory('Testing', $team);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$own->id, $shared->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE);

        $response->assertOk();
        $response->assertJsonPath('thread_categories.contact_id', $contact->id);
        $response->assertJsonPath('thread_categories.selected', [
            ['id' => $shared->id, 'name' => 'Mayorista'],
            ['id' => $own->id, 'name' => 'Testing'],
        ]);
        $response->assertJsonPath('thread_categories.available', [
            ['id' => $shared->id, 'name' => 'Mayorista'],
            ['id' => $own->id, 'name' => 'Testing'],
        ]);
    }

    public function test_thread_without_a_crm_contact_reports_no_categories(): void
    {
        [$token, , $team] = $this->inbox();
        $category = $this->contactsCategory('Testing', $team);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE);

        $response->assertOk();
        $response->assertJsonPath('thread_categories.contact_id', null);
        $response->assertJsonPath('thread_categories.selected', []);
        $response->assertJsonPath('thread_categories.available', [
            ['id' => $category->id, 'name' => 'Testing'],
        ]);
    }

    /**
     * Imports can leave another module's tag on a contact; the thread header speaks about the
     * contact, so only the contacts catalogue belongs there.
     */
    public function test_tags_from_other_modules_are_left_out(): void
    {
        [$token, , $team] = $this->inbox();
        $contactTag = $this->contactsCategory('Alfa', $team);
        $productModule = Module::firstOrCreate(['key' => 'products'], ['name' => 'products', 'description' => 'products', 'is_core' => 0, 'status' => 1, 'order' => 0]);
        $productTag = Category::create(['name' => 'Cerámica', 'module_id' => $productModule->id, 'team_id' => $team->id, 'status' => 1]);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$contactTag->id, $productTag->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE)
            ->assertOk()
            ->assertJsonPath('thread_categories.selected', [['id' => $contactTag->id, 'name' => 'Alfa']])
            ->assertJsonPath('thread_categories.available', [['id' => $contactTag->id, 'name' => 'Alfa']]);
    }

    public function test_archived_categories_are_left_out(): void
    {
        [$token, , $team] = $this->inbox();
        $live = $this->contactsCategory('Alfa', $team);
        $archived = $this->contactsCategory('Vieja', $team, status: 0);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$live->id, $archived->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE)
            ->assertOk()
            ->assertJsonPath('thread_categories.selected', [['id' => $live->id, 'name' => 'Alfa']])
            ->assertJsonPath('thread_categories.available', [['id' => $live->id, 'name' => 'Alfa']]);
    }

    public function test_the_same_category_cannot_be_attached_twice(): void
    {
        [, , $team] = $this->inbox();
        $category = $this->contactsCategory('Legacy', $team);
        $contact = $this->crmContact($team);
        $contact->categories()->attach($category->id);

        $this->expectException(QueryException::class);
        $contact->categories()->attach($category->id);
    }

    public function test_assigning_requires_authentication(): void
    {
        $this->patchJson('/api/chat/whatsapp-contact-categories', [
            'phone' => self::CLIENT_PHONE,
            'category_ids' => [1],
        ])->assertStatus(401);
    }

    public function test_assigning_a_category_attaches_it_to_the_crm_contact(): void
    {
        [$token, , $team] = $this->inbox();
        $category = $this->contactsCategory('Alfa', $team);
        $contact = $this->crmContact($team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-categories', [
                'phone' => self::CLIENT_PHONE,
                'category_ids' => [$category->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('contact_id', $contact->id)
            ->assertJsonPath('selected', [['id' => $category->id, 'name' => 'Alfa']]);

        $this->assertTrue($contact->fresh()->categories->contains('id', $category->id));
    }

    public function test_assigning_keeps_tags_from_other_modules(): void
    {
        [$token, , $team] = $this->inbox();
        $contactTag = $this->contactsCategory('Alfa', $team);
        $productModule = Module::firstOrCreate(['key' => 'products'], ['name' => 'products', 'description' => 'products', 'is_core' => 0, 'status' => 1, 'order' => 0]);
        $productTag = Category::create(['name' => 'Cerámica', 'module_id' => $productModule->id, 'team_id' => $team->id, 'status' => 1]);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$productTag->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-categories', [
                'phone' => self::CLIENT_PHONE,
                'category_ids' => [$contactTag->id],
            ])
            ->assertOk()
            ->assertJsonPath('selected', [['id' => $contactTag->id, 'name' => 'Alfa']]);

        $this->assertEqualsCanonicalizing(
            [$contactTag->id, $productTag->id],
            $contact->fresh()->categories->pluck('id')->all(),
        );
    }

    public function test_other_module_and_foreign_team_categories_are_refused(): void
    {
        [$token, , $team] = $this->inbox();
        $this->crmContact($team);
        $productModule = Module::firstOrCreate(['key' => 'products'], ['name' => 'products', 'description' => 'products', 'is_core' => 0, 'status' => 1, 'order' => 0]);
        $productTag = Category::create(['name' => 'Cerámica', 'module_id' => $productModule->id, 'team_id' => $team->id, 'status' => 1]);
        $foreign = $this->contactsCategory('Ajena', Team::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-categories', [
                'phone' => self::CLIENT_PHONE,
                'category_ids' => [$productTag->id],
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-categories', [
                'phone' => self::CLIENT_PHONE,
                'category_ids' => [$foreign->id],
            ])
            ->assertStatus(422);
    }

    public function test_assigning_without_a_crm_contact_is_refused(): void
    {
        [$token, , $team] = $this->inbox();
        $category = $this->contactsCategory('Alfa', $team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-categories', [
                'phone' => self::CLIENT_PHONE,
                'category_ids' => [$category->id],
            ])
            ->assertStatus(422);
    }

    /**
     * A token whose user has no team reaches this route since the menu middleware stopped
     * redirecting JSON callers, so it has to refuse instead of failing on a null team.
     */
    public function test_a_user_without_a_team_is_refused(): void
    {
        [, , $team] = $this->inbox();
        $this->contactsCategory('Alfa', $team);
        $this->crmContact($team);
        $token = User::factory()->create()->createToken('teamless')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE)
            ->assertForbidden();
    }

    /**
     * @return array{0: string, 1: User, 2: Team}
     */
    private function inbox(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Http::fake(['*' => Http::response(['pictures' => []], 200)]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);
        Module::firstOrCreate(['key' => 'contacts'], ['name' => 'contacts', 'description' => 'contacts', 'is_core' => 1, 'status' => 1, 'order' => 0]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        Conversation::create([
            'message_sid' => 'SM_thread_categories',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        return [$user->createToken('categories')->plainTextToken, $user, $team];
    }

    private function contactsCategory(string $name, ?Team $team, int $status = 1): Category
    {
        return Category::create([
            'name' => $name,
            'module_id' => Module::where('key', 'contacts')->value('id'),
            'team_id' => $team?->id,
            'status' => $status,
        ]);
    }

    private function crmContact(Team $team): Contact
    {
        return Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'creator_id' => $team->user_id,
            'responsible_id' => $team->user_id,
            'name' => 'Isabel',
            'surname' => 'Ayuso',
            'phone' => self::CLIENT_PHONE,
            'status_id' => 1,
        ]);
    }
}
