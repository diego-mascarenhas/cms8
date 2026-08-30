<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Conversation;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantChatService;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteAssistantInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
        ]);
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/chat/site-assistant-list')->assertStatus(401);
    }

    public function test_public_chat_is_listed_for_admin_and_collaborator(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$owner, $team, $automation] = $this->teamWithWebAssistant();
        $this->fakeAssistantReply('Hola del equipo');

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'ok!',
            'session_key' => 'web-session-alpha',
        ])->assertOk();

        $this->assertSame(2, SiteAssistantMessage::withoutGlobalScopes()->where('session_key', 'web-session-alpha')->count());

        $adminToken = $owner->createToken('admin-inbox')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/chat/site-assistant-list')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('conversations.0.session_key', 'web-session-alpha')
            ->assertJsonPath('conversations.0.name', 'Visitante')
            ->assertJsonPath('conversations.0.identified', false)
            ->assertJsonPath('conversations.0.last_message', 'Hola del equipo');

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/chat/site-assistant-messages/web-session-alpha')
            ->assertOk()
            ->assertJsonPath('session_key', 'web-session-alpha')
            ->assertJsonPath('messages.0.role', 'visitor')
            ->assertJsonPath('messages.0.body', 'ok!')
            ->assertJsonPath('messages.1.role', 'assistant')
            ->assertJsonPath('messages.1.body', 'Hola del equipo')
            ->assertJsonPath('thread_assistant.assistant_toggle_available', true)
            ->assertJsonPath('thread_assistant.prompt_key', null);

        $collaborator = User::factory()->create();
        Role::firstOrCreate(['name' => 'collaborator', 'guard_name' => 'web']);
        $collaborator->assignRole('collaborator');
        $team->users()->attach($collaborator->id, ['role' => 'collaborator']);
        $collaborator->forceFill(['current_team_id' => $team->id])->save();

        $this->withHeader('Authorization', 'Bearer '.$collaborator->createToken('collab-inbox')->plainTextToken)
            ->getJson('/api/chat/site-assistant-list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('conversations.0.session_key', 'web-session-alpha');
    }

    public function test_identified_visitor_appears_with_contact_name(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$owner, , $automation] = $this->teamWithWebAssistant();
        $this->fakeAssistantReply('Listo');

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'lucia@example.com',
            'name' => 'Lucía Pérez',
            'session_key' => 'web-session-lucia',
        ])->assertOk();

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'web-session-lucia',
        ])->assertOk();

        $token = $owner->createToken('admin-inbox')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/site-assistant-list')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('conversations', []);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('contacts.0.user_name', 'Lucía Pérez')
            ->assertJsonPath('contacts.0.channel', 'web')
            ->assertJsonPath('contacts.0.has_web', true)
            ->assertJsonPath('contacts.0.session_key', 'web-session-lucia');
    }

    public function test_identified_web_messages_join_the_whatsapp_thread(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$owner, $team, $automation] = $this->teamWithWebAssistant();
        $teamWa = '34999000111';
        $clientPhone = '34600111222';
        $team->setSetting('whatsapp_from', $teamWa);

        Http::fake(['*' => Http::response(['pictures' => []], 200)]);

        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Lucía',
            'surname' => 'Pérez',
            'email' => 'lucia@example.com',
            'phone' => $clientPhone,
            'status_id' => ContactStatus::query()->where('name', 'Lead')->value('id'),
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
        ]);

        Conversation::create([
            'message_sid' => 'SM_web_merge_1',
            'channel' => 'whatsapp',
            'from' => $clientPhone,
            'to' => $teamWa,
            'body' => 'Hola por WhatsApp',
            'status' => 'received',
            'direction' => 'inbound',
        ]);

        $this->fakeAssistantReply('Dale');
        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'lucia@example.com',
            'name' => 'Lucía Pérez',
            'session_key' => 'web-session-merge',
        ])->assertOk();

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola desde la web',
            'session_key' => 'web-session-merge',
        ])->assertOk();

        $this->assertSame($contact->id, SiteAssistantMessage::withoutGlobalScopes()
            ->where('session_key', 'web-session-merge')
            ->where('role', 'visitor')
            ->value('contact_id'));

        $token = $owner->createToken('admin-inbox')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('contacts.0.from', $clientPhone)
            ->assertJsonPath('contacts.0.has_web', true)
            ->assertJsonPath('contacts.0.contact_id', $contact->id);

        $thread = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/whatsapp-messages/'.$clientPhone)
            ->assertOk()
            ->json('messages');

        $bodies = collect($thread)->pluck('body')->all();
        $this->assertContains('Hola por WhatsApp', $bodies);
        $this->assertContains('Hola desde la web', $bodies);
        $this->assertContains('Dale', $bodies);
        $this->assertTrue(collect($thread)->contains(fn ($message) => ($message['channel'] ?? null) === 'web'));
    }

    public function test_client_does_not_see_anonymous_web_chats(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [, $team, $automation] = $this->teamWithWebAssistant();
        $this->fakeAssistantReply('Hola');

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'ok',
            'session_key' => 'web-anon',
        ])->assertOk();

        $client = User::factory()->create();
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client->assignRole('client');
        $team->users()->attach($client->id, ['role' => 'client']);
        $client->forceFill(['current_team_id' => $team->id])->save();

        $this->withHeader('Authorization', 'Bearer '.$client->createToken('client-inbox')->plainTextToken)
            ->getJson('/api/chat/site-assistant-list')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('conversations', []);
    }

    public function test_other_team_cannot_read_the_thread(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [, , $automation] = $this->teamWithWebAssistant();
        $this->fakeAssistantReply('Hola');

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'ok',
            'session_key' => 'web-private',
        ])->assertOk();

        $stranger = User::factory()->withPersonalTeam()->create();
        $stranger->forceFill(['current_team_id' => $stranger->ownedTeams()->first()->id])->save();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $stranger->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$stranger->createToken('other-team')->plainTextToken)
            ->getJson('/api/chat/site-assistant-messages/web-private')
            ->assertNotFound();
    }

    public function test_staff_can_reply_and_the_visitor_can_poll_it(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        [$owner, , $automation] = $this->teamWithWebAssistant();
        $this->fakeAssistantReply('Hola');

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Necesito un humano',
            'session_key' => 'web-staff-reply',
        ])->assertOk();

        $token = $owner->createToken('admin-inbox')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/chat/site-assistant-messages/web-staff-reply', [
                'message' => 'Te leo, dame un minuto.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.role', 'staff')
            ->assertJsonPath('message.body', 'Te leo, dame un minuto.')
            ->assertJsonPath('message.user_id', $owner->id);

        $this->assertSame(
            'Te leo, dame un minuto.',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'web-staff-reply')
                ->where('role', SiteAssistantMessage::ROLE_STAFF)
                ->value('body'),
        );
        $this->assertSame(
            $owner->id,
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'web-staff-reply')
                ->where('role', SiteAssistantMessage::ROLE_STAFF)
                ->value('user_id'),
        );

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/chat/site-assistant-messages/web-staff-reply')
            ->assertOk()
            ->assertJsonPath('messages.2.role', 'staff')
            ->assertJsonPath('messages.2.user_id', $owner->id)
            ->assertJsonPath('messages.2.from_assistant', false)
            ->assertJsonPath('messages.2.sender_avatar.initials', \App\Support\ChatMessageAvatar::initialsFromName((string) $owner->name));

        $this->getJson(route('api.embed.automation.messages', $automation->public_token).'?session_key=web-staff-reply')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['role' => 'staff', 'body' => 'Te leo, dame un minuto.']);
    }

    public function test_staff_reply_requires_authentication(): void
    {
        $this->postJson('/api/chat/site-assistant-messages/web-staff-reply', [
            'message' => 'Hola',
        ])->assertStatus(401);
    }

    public function test_staff_can_pin_a_prompt_on_a_silent_web_thread(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $this->seed(ModuleSeeder::class);
        [$owner, $team, $automation] = $this->teamWithWebAssistant();
        $team->setSetting(TeamSiteAssistantPromptService::SETTING_KEY, TeamSiteAssistantPromptService::OFF_KEY);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'web-pin-prompt',
        ])
            ->assertOk()
            ->assertJsonPath('reply', '');

        $this->assertSame(1, SiteAssistantMessage::withoutGlobalScopes()->where('session_key', 'web-pin-prompt')->count());

        $token = $owner->createToken('admin-inbox')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/chat/site-assistant-messages/web-pin-prompt/assistant', [
                'on' => true,
                'prompt_key' => 'invoices:collections',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('thread_assistant.prompt_key', 'invoices:collections')
            ->assertJsonPath('thread_assistant.assistant_contact_enabled', true);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(fn ($message, $teamId, $image, $audio, $voice, $promptKey) => $promptKey === 'invoices:collections')
                ->andReturn([
                    'response' => 'Te ayudo con eso',
                    'routed_to' => 'invoices:collections',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Quiero una cita',
            'session_key' => 'web-pin-prompt',
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Te ayudo con eso');
    }

    public function test_updating_the_web_thread_prompt_requires_authentication(): void
    {
        $this->patchJson('/api/chat/site-assistant-messages/web-pin-prompt/assistant', [
            'on' => true,
            'prompt_key' => 'contacts:landing',
        ])->assertStatus(401);
    }

    /**
     * @return array{0: User, 1: Team, 2: Automation}
     */
    private function teamWithWebAssistant(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->first();
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $owner->assignRole('admin');

        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => TeamSiteAssistantPromptService::EMBED_SLUG,
            'is_active' => true,
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        return [$owner, $team, $automation];
    }

    private function fakeAssistantReply(string $reply): void
    {
        $this->mock(AssistantChatService::class, function ($mock) use ($reply): void
        {
            $mock->shouldReceive('run')->andReturn([
                'response' => $reply,
                'routed_to' => null,
            ]);
        });
    }
}
