<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\User;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

        Http::fake([
            '*' => Http::response(['pictures' => []], 200),
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

    public function test_whatsapp_list_returns_stored_whatsapp_profile_photo(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Storage::fake('public');
        Http::fake([
            '*' => Http::response(['pictures' => []], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600111222';
        Storage::disk('public')->put('whatsapp/avatars/'.$team->id.'/'.$clientPhone.'.jpg', 'avatar');
        Conversation::create([
            'message_sid' => 'SM_api_chat_list_photo_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list');

        $response->assertOk();
        $first = $response->json('contacts.0');
        $this->assertIsArray($first);
        $this->assertStringContainsString('whatsapp/avatars/'.$team->id.'/'.$clientPhone.'.jpg', (string) $first['user_photo']);
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
                'prompt_key',
                'default_prompt_key',
                'prompts',
            ],
        ]);
        $this->assertCount(1, $response->json('messages'));
        $this->assertSame('First', $response->json('messages.0.body'));
        $this->assertFalse($response->json('messages.0.transcribed_audio'));
        $this->assertFalse($response->json('messages.0.from_assistant'));
        $this->assertFalse($response->json('thread_assistant.assistant_toggle_available'));
    }

    public function test_whatsapp_messages_uses_assistant_avatar_for_unattributed_outbound(): void
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
        $clientPhone = '34600888999';
        Conversation::create([
            'message_sid' => 'SM_api_chat_assistant_out_1',
            'channel' => 'whatsapp',
            'from' => $teamWa,
            'to' => $clientPhone,
            'body' => 'Respuesta automática',
            'status' => 'sent',
            'direction' => 'outbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone);

        $response->assertOk();
        $this->assertTrue($response->json('messages.0.from_assistant'));
        $this->assertSame('robot', $response->json('messages.0.sender_avatar.icon'));
    }

    public function test_whatsapp_messages_uses_agent_avatar_for_attributed_outbound(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create(['name' => 'Diego Perez']);
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $clientPhone = '34600999000';
        Conversation::create([
            'message_sid' => 'SM_api_chat_agent_out_1',
            'channel' => 'whatsapp',
            'from' => $teamWa,
            'to' => $clientPhone,
            'body' => 'Perfecto!',
            'status' => 'sent',
            'direction' => 'outbound',
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone);

        $response->assertOk();
        $this->assertFalse($response->json('messages.0.from_assistant'));
        $this->assertSame('DP', $response->json('messages.0.sender_avatar.initials'));
    }

    public function test_whatsapp_messages_marks_transcribed_audio(): void
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
        $clientPhone = '34600777888';
        Conversation::create([
            'message_sid' => 'SM_api_chat_audio_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => '[Audio]: Hola.',
            'status' => 'received',
            'direction' => 'inbound',
            'metadata' => ['TranscribedAudio' => '1'],
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone);

        $response->assertOk();
        $this->assertTrue($response->json('messages.0.transcribed_audio'));
        $this->assertSame('[Audio]: Hola.', $response->json('messages.0.body'));
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
        $team->setSetting('assistant_auto_respond', '1');
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
        $this->assertNull($contact->inboundChatAssistantPromptKey());
    }

    public function test_whatsapp_contact_assistant_patch_pins_prompt_and_none_disables(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class, ModuleSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $team->setSetting('assistant_auto_respond', '1');
        $clientPhone = '34600555777';
        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);
        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Reservá citas.',
            'is_active' => true,
            'order' => 0,
        ]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => $clientPhone,
            'status_id' => $leadId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        Conversation::create([
            'message_sid' => 'SM_api_assistant_prompt_1',
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
                'prompt_key' => 'chat:citas_y_ventas',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'assistant_inbound_enabled' => true,
                'prompt_key' => 'chat:citas_y_ventas',
            ]);

        $contact->refresh();
        $this->assertTrue($contact->allowsInboundChatAssistant());
        $this->assertSame('chat:citas_y_ventas', $contact->inboundChatAssistantPromptKey());

        $thread = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone)
            ->assertOk()
            ->assertJsonPath('thread_assistant.prompt_key', 'chat:citas_y_ventas');

        $promptKeys = collect($thread->json('thread_assistant.prompts'))->pluck('key');
        $this->assertTrue($promptKeys->contains('chat:citas_y_ventas'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-assistant', [
                'phone' => $clientPhone,
                'on' => false,
                'prompt_key' => '',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'assistant_inbound_enabled' => false,
                'prompt_key' => null,
            ]);

        $contact->refresh();
        $this->assertFalse($contact->allowsInboundChatAssistant());
        $this->assertNull($contact->inboundChatAssistantPromptKey());
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

    public function test_whatsapp_status_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-status')->assertStatus(401);
    }

    public function test_whatsapp_status_returns_json_when_authenticated(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $token = $user->createToken('idoneo-assistant-test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-status')
            ->assertOk()
            ->assertJsonStructure([
                'driver',
                'status',
                'number',
                'numberFormatted',
                'teamNumber',
                'teamNumberFormatted',
                'isTeamConnected',
            ]);
    }

    public function test_whatsapp_status_reports_team_connected_for_local_driver(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/status*' => Http::response(['status' => 'connected', 'number' => '34613194131'], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $token = $user->createToken('idoneo-assistant-test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-status')
            ->assertOk()
            ->assertJson([
                'status' => 'connected',
                'isTeamConnected' => true,
            ]);

        $this->assertSame('34613194131', $team->fresh()->getWhatsAppFrom());
    }

    public function test_whatsapp_qr_image_requires_authentication(): void
    {
        $this->getJson('/api/chat/whatsapp-qr-image')->assertStatus(401);
    }

    public function test_whatsapp_qr_image_returns_204_when_node_has_no_qr_yet(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        Http::fake([
            'wa.test/qr.png*' => Http::response('', 404),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $token = $user->createToken('idoneo-assistant-test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/chat/whatsapp-qr-image')
            ->assertNoContent();
    }

    public function test_whatsapp_qr_image_returns_png_when_node_serves_qr(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Config::set('whatsapp.driver', 'local');
        Config::set('whatsapp.local.base_url', 'http://wa.test');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $body = str_repeat($png, 40);

        Http::fake([
            'wa.test/qr.png*' => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $token = $user->createToken('idoneo-assistant-test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/chat/whatsapp-qr-image')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_whatsapp_refresh_qr_requires_authentication(): void
    {
        $this->postJson('/api/chat/whatsapp-refresh-qr')->assertStatus(401);
    }

    public function test_whatsapp_thread_locks_assistant_without_paid_plan(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['humano_pricing.require_paid_plan_for_ai' => true]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $team->setSetting('assistant_auto_respond', '1');
        $clientPhone = '34600777888';
        $leadId = ContactStatus::where('name', 'Lead')->firstOrFail()->id;
        Contact::factory()->create([
            'team_id' => $team->id,
            'phone' => $clientPhone,
            'status_id' => $leadId,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        Conversation::create([
            'message_sid' => 'SM_api_assistant_lock_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone)
            ->assertOk()
            ->assertJsonPath('thread_assistant.assistant_plan_active', false)
            ->assertJsonPath('thread_assistant.assistant_locked_reason', 'plan')
            ->assertJsonPath('thread_assistant.assistant_inbound_enabled', false)
            ->assertJsonPath('thread_assistant.assistant_toggle_available', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/whatsapp-contact-assistant', [
                'phone' => $clientPhone,
                'on' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('assistant_locked_reason', 'plan');
    }

    public function test_whatsapp_send_does_not_start_registration_for_new_named_contact(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-message' => Http::response(['success' => true, 'id' => 'wa-manual-1'], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, ContactStatusSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $team->setSetting('whatsapp_from', '34999000111');
        $clientPhone = '34600111333';
        Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Ana',
            'surname' => 'Pérez',
            'phone' => $clientPhone,
            'email' => null,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
            'status_id' => 1,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => 'Hola Ana',
            ])
            ->assertOk()
            ->assertJsonMissing(['registration' => true]);

        $this->assertDatabaseHas('conversations', [
            'channel' => 'whatsapp',
            'to' => $clientPhone,
            'direction' => 'outbound',
            'body' => 'Hola Ana',
        ]);
        Http::assertSent(function ($request): bool
        {
            return str_contains($request->url(), '/send-message')
                && ($request->data()['body'] ?? '') === 'Hola Ana';
        });
    }

    public function test_whatsapp_send_uploads_attachment_and_keeps_it_on_the_thread(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Storage::fake('public');
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-media' => Http::response(['success' => true], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $team->setSetting('assistant_auto_respond', '0');
        $clientPhone = '34600111222';

        $token = $user->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('foto.jpg', 40, 40);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => 'Mirá esta foto',
                'attachments' => [$file],
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('conversations', [
            'channel' => 'whatsapp',
            'from' => $teamWa,
            'to' => $clientPhone,
            'direction' => 'outbound',
            'body' => 'Mirá esta foto',
        ]);

        $conversation = Conversation::query()
            ->where('to', $clientPhone)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();
        $this->assertNotNull($conversation);
        $this->assertNotEmpty($conversation->media);
        $this->assertStringContainsString('foto.jpg', (string) ($conversation->media[0]['name'] ?? ''));
        $this->assertNotEmpty($conversation->media[0]['url'] ?? null);
        $this->assertSame(1, Conversation::query()->where('direction', 'outbound')->count());
    }

    public function test_whatsapp_send_attachment_asks_assistant_when_enabled(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        Storage::fake('public');
        config(['whatsapp.driver' => 'local']);
        config(['whatsapp.local.base_url' => 'http://127.0.0.1:3000']);

        Http::fake([
            'http://127.0.0.1:3000/status*' => Http::response(['status' => 'connected', 'number' => '34999000111'], 200),
            'http://127.0.0.1:3000/send-media' => Http::response(['success' => true], 200),
            'http://127.0.0.1:3000/send-message' => Http::response(['success' => true, 'id' => 'wa-doc-reply-1'], 200),
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([CountrySeeder::class, LanguageSeeder::class, \Database\Seeders\SourceSeeder::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');
        $teamWa = '34999000111';
        $team->setSetting('whatsapp_from', $teamWa);
        $team->setSetting('assistant_auto_respond', '1');
        $clientPhone = '34600111222';

        $token = $user->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->image('tarjeta.jpg', 40, 40);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/chat/whatsapp-send', [
                'to' => $clientPhone,
                'message' => '',
                'attachments' => [$file],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, Conversation::query()->where('direction', 'outbound')->where('to', $clientPhone)->count());
        $attachment = Conversation::query()
            ->where('to', $clientPhone)
            ->where('direction', 'outbound')
            ->where('metadata->source', 'chat_attachments')
            ->first();
        $this->assertNotNull($attachment);
        $this->assertSame('', (string) $attachment->body);
        $this->assertNotEmpty($attachment->media);
        $this->assertTrue(
            Conversation::query()
                ->where('direction', 'outbound')
                ->where('to', $clientPhone)
                ->get()
                ->contains(fn (Conversation $row): bool => str_contains((string) $row->body, 'Recibi tu documento')),
        );
    }
}
