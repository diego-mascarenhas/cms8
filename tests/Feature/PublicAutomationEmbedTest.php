<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Team;
use App\Services\AssistantChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAutomationEmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_welcome_message(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'name' => 'Web support',
            'channels' => Automation::normalizeChannels(['api' => true]),
            'settings' => ['welcome_message' => 'Welcome visitor'],
        ]);

        $this->getJson(route('api.embed.automation.meta', $automation->public_token))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'name' => 'Web support',
                'welcome_message' => 'Welcome visitor',
            ]);
    }

    public function test_public_chat_runs_automation(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        $this->mock(AssistantChatService::class, function ($mock) use ($team): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->with('Need help', $team->id, null, null, false, 'contacts:landing')
                ->andReturn([
                    'response' => 'Sure',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Need help',
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'reply' => 'Sure',
                'response' => 'Sure',
                'demo' => false,
            ]);
    }

    public function test_public_chat_stores_mobile_origin(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Sure',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->withHeader('User-Agent', 'Dart/3.5 (dart:io)')
            ->postJson(route('api.embed.automation.assistant', $automation->public_token), [
                'message' => 'Desde Mobile!',
                'session_key' => 'mobile-ua',
            ])
            ->assertOk();

        $this->assertSame('mobile', \App\Models\SiteAssistantMessage::withoutGlobalScopes()
            ->where('session_key', 'mobile-ua')
            ->where('role', 'visitor')
            ->value('channel'));
    }

    public function test_public_chat_404_for_invalid_token(): void
    {
        $this->postJson(route('api.embed.automation.assistant', 'invalidtokeninvalidtokeninvalidtokeninvalidtokeninvalidtoken12'), [
            'message' => 'Hi',
        ])->assertStatus(404);
    }

    public function test_public_chat_403_when_api_disabled(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'channels' => Automation::normalizeChannels(['whatsapp' => true]),
        ]);

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hi',
        ])->assertStatus(403);
    }
}
