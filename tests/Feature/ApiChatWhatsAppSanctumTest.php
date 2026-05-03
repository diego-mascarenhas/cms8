<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiChatWhatsAppSanctumTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_list_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-list')->assertStatus(401);
    }

    public function test_whatsapp_messages_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-messages/34111222333')->assertStatus(401);
    }

    public function test_whatsapp_list_returns_json_when_authenticated(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600111222';
        Conversation::create([
            'message_sid' => 'SM_api_chat_list_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola mundo',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $response->assertOk();
        $response->assertJsonStructure(['contacts']);
        $contacts = $response->json('contacts');
        $this->assertNotEmpty($contacts);
        $first = $contacts[0];
        $this->assertSame(preg_replace('/[^0-9]/', '', $clientPhone), $first['from']);
        $this->assertArrayHasKey('last_message', $first);
        $this->assertArrayHasKey('last_message_time', $first);
        $this->assertArrayHasKey('unread_count', $first);
        $this->assertArrayHasKey('assistant_toggle_available', $first);
        $this->assertArrayHasKey('assistant_inbound_enabled', $first);
    }

    public function test_whatsapp_messages_returns_messages_when_authenticated(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600333444';
        Conversation::create([
            'message_sid' => 'SM_api_chat_msg_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'First',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone);

        $response->assertOk();
        $response->assertJsonStructure([
            'messages',
            'thread_assistant' => [
                'contact_id',
                'assistant_inbound_enabled',
                'assistant_toggle_available',
            ],
        ]);
        $this->assertCount(1, $response->json('messages'));
        $this->assertSame('First', $response->json('messages.0.body'));
        $this->assertFalse($response->json('thread_assistant.assistant_toggle_available'));
    }

    public function test_whatsapp_contact_assistant_patch_updates_contact_when_crm_exists(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600555666';
        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => $clientPhone,
            'status_id' => $leadId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        Conversation::create([
            'message_sid' => 'SM_api_assistant_toggle_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-assistant', [
                'phone' => $clientPhone,
                'on' => false,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'assistant_inbound_enabled' => false,
                'assistant_toggle_available' => true,
            ]);

        $contact->refresh();
        $this->assertFalse($contact->allowsInboundChatAssistant());
    }

    public function test_whatsapp_contact_assistant_patch_returns_422_without_crm_contact(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $orphanPhone = '34600777888';
        Conversation::create([
            'message_sid' => 'SM_api_no_contact_1',
            'channel' => 'whatsapp',
            'from' => $orphanPhone,
            'to' => $teamWa,
            'body' => 'Sin CRM',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-assistant', [
                'phone' => $orphanPhone,
                'on' => false,
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
