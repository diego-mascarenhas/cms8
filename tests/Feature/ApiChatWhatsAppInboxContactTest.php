<?php

namespace Tests\Feature;

use App\Jobs\FetchWhatsAppProfilePhotoJob;
use App\Models\Category;
use App\Models\Contact;
use App\Models\ContactSentimentHistory;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ContactSentimentSeeder;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppInboxContactTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_NUMBER = '34999000111';

    private const CLIENT_PHONE = '34600111222';

    public function test_update_requires_authentication(): void
    {
        $this->patchJson('/api/chat/whatsapp-contact', [
            'phone' => self::CLIENT_PHONE,
            'name' => 'Ana Pérez',
            'status_id' => 1,
        ])->assertStatus(401);
    }

    public function test_thread_exposes_contact_name_status_and_catalog(): void
    {
        [$token, , $team] = $this->inbox();
        $status = ContactStatus::query()->where('name', 'Cliente')->first();
        $this->assertNotNull($status);
        $contact = $this->crmContact($team, ['name' => 'Diego', 'surname' => 'Mascarenhas', 'status_id' => $status->id]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE);

        $response->assertOk();
        $response->assertJsonPath('thread_contact.contact_id', $contact->id);
        $response->assertJsonPath('thread_contact.name', 'Diego Mascarenhas');
        $response->assertJsonPath('thread_contact.phone', self::CLIENT_PHONE);
        $response->assertJsonPath('thread_contact.status_id', $status->id);
        $response->assertJsonPath('thread_contact.email', null);
        $this->assertContains('Lead', collect($response->json('thread_contact.statuses'))->pluck('name')->all());
        $this->assertNotContains('Finalizado', collect($response->json('thread_contact.statuses'))->pluck('name')->all());
    }

    public function test_thread_exposes_contact_email_and_hides_placeholder(): void
    {
        [$token, , $team] = $this->inbox();
        $this->crmContact($team, ['email' => 'diego@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.self::CLIENT_PHONE)
            ->assertOk()
            ->assertJsonPath('thread_contact.email', 'diego@example.com');

        $placeholder = $this->crmContact($team, [
            'phone' => '34600888777',
            'email' => '34600888777@chat.placeholder',
        ]);
        Conversation::create([
            'message_sid' => 'SM_inbox_placeholder_email',
            'channel' => 'whatsapp',
            'from' => '34600888777',
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/34600888777')
            ->assertOk()
            ->assertJsonPath('thread_contact.contact_id', $placeholder->id)
            ->assertJsonPath('thread_contact.email', null);
    }

    public function test_list_includes_the_contact_sentiment_on_the_avatar_payload(): void
    {
        [$token, , $team] = $this->inbox();
        $this->seed(ContactSentimentSeeder::class);
        $contact = $this->crmContact($team, ['name' => 'Diego', 'surname' => 'Mascarenhas']);
        ContactSentimentHistory::query()->create([
            'contact_id' => $contact->id,
            'sentiment_id' => 2,
            'notes' => 'Análisis automático de daily: molesto por la espera',
        ]);

        $row = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->json('contacts.0');

        $this->assertSame(self::CLIENT_PHONE, $row['from']);
        $this->assertSame(2, $row['sentiment']['id']);
        $this->assertSame('Negativo', $row['sentiment']['name']);
        $this->assertSame('🙁', $row['sentiment']['emoji']);
    }

    public function test_list_includes_the_contact_catalog(): void
    {
        [$token, , $team] = $this->inbox();
        $category = $this->contactsCategory('Alfa', $team);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $response->assertOk();
        $this->assertContains('Lead', collect($response->json('contact_catalog.statuses'))->pluck('name')->all());
        $this->assertNotContains('Finalizado', collect($response->json('contact_catalog.statuses'))->pluck('name')->all());
        $lead = collect($response->json('contact_catalog.statuses'))->firstWhere('name', 'Lead');
        $this->assertSame('#28c76f', $lead['color']);
        $response->assertJsonPath('contact_catalog.categories', [
            ['id' => $category->id, 'name' => 'Alfa', 'color' => null],
        ]);
    }

    public function test_list_can_filter_conversations_by_contact_category(): void
    {
        [$token, , $team] = $this->inbox();
        $alfa = $this->contactsCategory('Alfa', $team);
        $this->contactsCategory('Beta', $team);
        $tagged = $this->crmContact($team, ['name' => 'Ana']);
        $tagged->categories()->sync([$alfa->id]);
        $this->crmContact($team, ['name' => 'Luis', 'phone' => '34600999888']);
        Conversation::create([
            'message_sid' => 'SM_inbox_other',
            'channel' => 'whatsapp',
            'from' => '34600999888',
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $all = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk();
        $this->assertCount(2, $all->json('contacts'));
        $ana = collect($all->json('contacts'))->firstWhere('from', self::CLIENT_PHONE);
        $this->assertSame([$alfa->id], $ana['category_ids']);

        $filtered = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list?category_id='.$alfa->id)
            ->assertOk();
        $this->assertSame([self::CLIENT_PHONE], collect($filtered->json('contacts'))->pluck('from')->all());
        $filtered->assertJsonPath('total', 1);
    }

    public function test_update_changes_name_status_and_categories_but_not_the_phone(): void
    {
        [$token, , $team] = $this->inbox();
        $cliente = ContactStatus::query()->where('name', 'Cliente')->first();
        $this->assertNotNull($cliente);
        $keep = $this->contactsCategory('Alfa', $team);
        $drop = $this->contactsCategory('Vieja', $team);
        $contact = $this->crmContact($team, ['name' => 'Diego', 'surname' => 'eventos', 'status_id' => 1]);
        $contact->categories()->sync([$drop->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Diego Mascarenhas',
                'status_id' => $cliente->id,
                'category_ids' => [$keep->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('contact.name', 'Diego Mascarenhas')
            ->assertJsonPath('contact.phone', self::CLIENT_PHONE)
            ->assertJsonPath('contact.status_id', $cliente->id)
            ->assertJsonPath('thread_contact.name', 'Diego Mascarenhas')
            ->assertJsonPath('thread_categories.selected', [['id' => $keep->id, 'name' => 'Alfa', 'color' => null]]);

        $contact->refresh();
        $this->assertSame('Diego', $contact->name);
        $this->assertSame('Mascarenhas', $contact->surname);
        $this->assertSame(self::CLIENT_PHONE, (string) $contact->phone);
        $this->assertSame($cliente->id, $contact->status_id);
        $this->assertEqualsCanonicalizing([$keep->id], $contact->categories->pluck('id')->all());
    }

    public function test_update_fetches_whatsapp_profile_photo(): void
    {
        Bus::fake();

        [$token, , $team] = $this->inbox();
        $this->crmContact($team, ['name' => 'Diego', 'surname' => 'Mascarenhas', 'status_id' => 1]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Diego Mascarenhas',
                'status_id' => 1,
            ])
            ->assertOk();

        Bus::assertDispatched(FetchWhatsAppProfilePhotoJob::class, function (FetchWhatsAppProfilePhotoJob $job) use ($team): bool
        {
            return $job->teamId === (int) $team->id && $job->phone === self::CLIENT_PHONE;
        });
    }

    public function test_update_saves_clears_and_validates_email(): void
    {
        [$token, , $team] = $this->inbox();
        $contact = $this->crmContact($team, ['email' => 'viejo@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'email' => 'Isabel@Example.com',
            ])
            ->assertOk()
            ->assertJsonPath('contact.email', 'isabel@example.com')
            ->assertJsonPath('thread_contact.email', 'isabel@example.com');

        $this->assertSame('isabel@example.com', $contact->fresh()->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'email' => 'no-es-un-email',
            ])
            ->assertStatus(422);

        $this->assertSame('isabel@example.com', $contact->fresh()->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
            ])
            ->assertOk();

        $this->assertSame('isabel@example.com', $contact->fresh()->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'email' => '',
            ])
            ->assertOk()
            ->assertJsonPath('contact.email', null)
            ->assertJsonPath('thread_contact.email', null);

        $this->assertNull($contact->fresh()->email);
    }

    public function test_update_keeps_tags_from_other_modules_when_categories_change(): void
    {
        [$token, , $team] = $this->inbox();
        $contactTag = $this->contactsCategory('Alfa', $team);
        $productModule = Module::firstOrCreate(['key' => 'products'], ['name' => 'products', 'description' => 'products', 'is_core' => 0, 'status' => 1, 'order' => 0]);
        $productTag = Category::create(['name' => 'Cerámica', 'module_id' => $productModule->id, 'team_id' => $team->id, 'status' => 1]);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$productTag->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'category_ids' => [$contactTag->id],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$contactTag->id, $productTag->id],
            $contact->fresh()->categories->pluck('id')->all(),
        );
    }

    public function test_update_can_clear_contact_categories(): void
    {
        [$token, , $team] = $this->inbox();
        $category = $this->contactsCategory('Alfa', $team);
        $contact = $this->crmContact($team);
        $contact->categories()->sync([$category->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'category_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('thread_categories.selected', []);

        $this->assertSame([], $contact->fresh()->categories->pluck('id')->all());
    }

    public function test_update_refuses_an_unknown_status_or_category(): void
    {
        [$token, , $team] = $this->inbox();
        $this->crmContact($team);
        $foreign = $this->contactsCategory('Ajena', Team::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 9999,
            ])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
                'category_ids' => [$foreign->id],
            ])
            ->assertStatus(422);
    }

    public function test_update_without_a_crm_contact_is_refused(): void
    {
        [$token] = $this->inbox();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact', [
                'phone' => self::CLIENT_PHONE,
                'name' => 'Isabel Ayuso',
                'status_id' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_start_contact_can_set_status_and_categories(): void
    {
        [$token, $user, $team] = $this->inbox();
        $cliente = ContactStatus::query()->where('name', 'Cliente')->first();
        $this->assertNotNull($cliente);
        $category = $this->contactsCategory('Alfa', $team);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-start-contact', [
                'name' => 'Ana Pérez',
                'phone' => '600333444',
                'status_id' => $cliente->id,
                'category_ids' => [$category->id],
            ])
            ->assertCreated()
            ->assertJsonPath('contact.name', 'Ana Pérez')
            ->assertJsonPath('contact.status_id', $cliente->id);

        $created = Contact::query()->where('team_id', $user->currentTeam->id)->where('phone', '34600333444')->first();
        $this->assertNotNull($created);
        $this->assertSame($cliente->id, $created->status_id);
        $this->assertTrue($created->categories->contains('id', $category->id));
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
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class, ContactSentimentSeeder::class]);
        Module::firstOrCreate(['key' => 'contacts'], ['name' => 'contacts', 'description' => 'contacts', 'is_core' => 1, 'status' => 1, 'order' => 0]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', self::TEAM_NUMBER);

        Conversation::create([
            'message_sid' => 'SM_inbox_contact',
            'channel' => 'whatsapp',
            'from' => self::CLIENT_PHONE,
            'to' => self::TEAM_NUMBER,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        return [$user->createToken('inbox-contact')->plainTextToken, $user, $team];
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crmContact(Team $team, array $overrides = []): Contact
    {
        return Contact::withoutGlobalScopes()->create(array_merge([
            'team_id' => $team->id,
            'creator_id' => $team->user_id,
            'responsible_id' => $team->user_id,
            'name' => 'Isabel',
            'surname' => 'Ayuso',
            'phone' => self::CLIENT_PHONE,
            'status_id' => 1,
        ], $overrides));
    }
}
