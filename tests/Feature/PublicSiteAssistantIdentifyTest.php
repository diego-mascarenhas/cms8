<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use App\Services\AssistantChatService;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteAssistantIdentifyTest extends TestCase
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

    public function test_visitor_can_identify_without_auth_and_creates_a_lead(): void
    {
        [$team, $automation] = $this->webAssistant();

        $response = $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'ana.visitante@example.com',
            'name' => 'Ana García',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('visitor.identified', true)
            ->assertJsonPath('visitor.first_name', 'Ana');

        $sessionKey = $response->json('session_key');
        $this->assertIsString($sessionKey);

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'ana.visitante@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('Ana', $contact->name);
        $this->assertSame('García', $contact->surname);
        $this->assertSame('Lead', optional(ContactStatus::find($contact->status_id))->name);
        $this->assertSame($contact->id, data_get(
            AutomationFlowSession::query()
                ->where('automation_id', $automation->id)
                ->where('external_key', $sessionKey)
                ->first()
                ?->meta,
            'contact_id',
        ));
    }

    public function test_identify_binds_an_existing_client_by_email(): void
    {
        [$team, $automation] = $this->webAssistant();
        $contact = Contact::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Diego',
            'surname' => 'Cliente',
            'email' => 'diego.cliente@example.com',
            'status_id' => ContactStatus::query()->where('name', 'Cliente')->value('id'),
            'creator_id' => $team->user_id,
            'responsible_id' => $team->user_id,
        ]);

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'Diego.Cliente@example.com',
            'name' => 'Otro Nombre',
            'session_key' => 'visitor-session-1',
        ])
            ->assertOk()
            ->assertJsonPath('visitor.first_name', 'Diego');

        $this->assertSame(1, Contact::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertSame('Diego', $contact->fresh()->name);
        $this->assertSame($contact->id, data_get(
            AutomationFlowSession::query()->where('external_key', 'visitor-session-1')->first()?->meta,
            'contact_id',
        ));
    }

    public function test_chat_returns_identified_visitor_after_login(): void
    {
        [, $automation] = $this->webAssistant();

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Hola Diego',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'diego.cliente@example.com',
            'name' => 'Diego',
            'session_key' => 'visitor-session-2',
        ])->assertOk();

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'visitor-session-2',
        ])
            ->assertOk()
            ->assertJsonPath('visitor.identified', true)
            ->assertJsonPath('visitor.first_name', 'Diego');
    }

    public function test_anonymous_chat_does_not_require_identify(): void
    {
        [, $automation] = $this->webAssistant();

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Contame',
                    'routed_to' => null,
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('visitor.identified', false)
            ->assertJsonPath('visitor.first_name', null);
    }

    public function test_force_off_keeps_the_message_and_skips_the_model(): void
    {
        [$team, $automation] = $this->webAssistant();
        $team->setSetting(TeamSiteAssistantPromptService::SETTING_KEY, TeamSiteAssistantPromptService::FORCE_OFF_KEY);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Quiero una cita',
            'session_key' => 'paused-web-session',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reply', '');

        $this->assertSame(1, SiteAssistantMessage::withoutGlobalScopes()->where('session_key', 'paused-web-session')->count());
        $this->assertSame(
            'Quiero una cita',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'paused-web-session')
                ->where('role', 'visitor')
                ->value('body'),
        );
    }

    public function test_silent_default_skips_the_model_for_anonymous_web_visitors(): void
    {
        [$team, $automation] = $this->webAssistant();
        $team->setSetting(TeamSiteAssistantPromptService::SETTING_KEY, TeamSiteAssistantPromptService::OFF_KEY);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'silent-web-session',
        ])
            ->assertOk()
            ->assertJsonPath('reply', '');

        $this->assertSame(1, SiteAssistantMessage::withoutGlobalScopes()->where('session_key', 'silent-web-session')->count());
        $this->assertSame(
            'Hola',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'silent-web-session')
                ->where('role', 'visitor')
                ->value('body'),
        );
        $this->assertNull(
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'silent-web-session')
                ->where('role', 'assistant')
                ->value('id'),
        );
    }

    public function test_identify_requires_email(): void
    {
        [, $automation] = $this->webAssistant();

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'name' => 'Ana',
        ])->assertStatus(422);
    }

    /**
     * @return array{0: Team, 1: Automation}
     */
    private function webAssistant(): array
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => TeamSiteAssistantPromptService::EMBED_SLUG,
            'is_active' => true,
            'entry_prompt_key' => 'contacts:landing',
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        return [$team, $automation];
    }
}
