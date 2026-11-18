<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\AutomationFlowSession;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\SiteAssistantMessage;
use App\Models\Team;
use App\Services\AssistantChatService;
use App\Services\SiteAssistantInboxIdentityService;
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

    public function test_force_off_still_replies_on_an_active_embed(): void
    {
        [$team, $automation] = $this->webAssistant();
        $team->setSetting(TeamSiteAssistantPromptService::SETTING_KEY, TeamSiteAssistantPromptService::FORCE_OFF_KEY);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Te armo el sitio',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Quiero una cita',
            'session_key' => 'paused-web-session',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reply', 'Te armo el sitio');

        $this->assertSame(
            'Quiero una cita',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'paused-web-session')
                ->where('role', 'visitor')
                ->value('body'),
        );
    }

    public function test_silent_default_still_replies_on_an_active_embed(): void
    {
        [$team, $automation] = $this->webAssistant();
        $team->setSetting(TeamSiteAssistantPromptService::SETTING_KEY, TeamSiteAssistantPromptService::OFF_KEY);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'response' => 'Hola',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'silent-web-session',
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Hola');

        $this->assertSame(
            'Hola',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'silent-web-session')
                ->where('role', 'visitor')
                ->value('body'),
        );
        $this->assertNotNull(
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

    public function test_client_claim_asks_for_identity_without_the_model(): void
    {
        [, $automation] = $this->webAssistant();

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Ya soy cliente',
            'session_key' => 'claim-session',
        ])
            ->assertOk()
            ->assertJsonPath('reply', app(SiteAssistantInboxIdentityService::class)->askMessage())
            ->assertJsonPath('visitor.identified', false);

        $this->assertSame(
            'Ya soy cliente',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'claim-session')
                ->where('role', 'visitor')
                ->value('body'),
        );
    }

    public function test_identity_reply_after_the_ask_identifies_the_visitor(): void
    {
        [$team, $automation] = $this->webAssistant();
        $identity = app(SiteAssistantInboxIdentityService::class);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Ya soy cliente',
            'session_key' => 'claim-identify',
        ])->assertOk();

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Ana Pérez, ana.perez@example.com',
            'session_key' => 'claim-identify',
        ])
            ->assertOk()
            ->assertJsonPath('reply', $identity->identifiedMessage())
            ->assertJsonPath('visitor.identified', true)
            ->assertJsonPath('visitor.first_name', 'Ana');

        $this->assertNotNull(
            Contact::withoutGlobalScopes()
                ->where('team_id', $team->id)
                ->where('email', 'ana.perez@example.com')
                ->first(),
        );

        $this->getJson(route('api.embed.automation.messages', $automation->public_token).'?session_key=claim-identify')
            ->assertOk()
            ->assertJsonPath('visitor.identified', true)
            ->assertJsonPath('visitor.first_name', 'Ana');
    }

    public function test_chat_persists_attribution_first_touch_on_session_meta(): void
    {
        [, $automation] = $this->webAssistant();

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->twice()
                ->andReturn([
                    'response' => 'Hola',
                    'routed_to' => 'contacts:landing',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola',
            'session_key' => 'attr-session',
            'context' => 'Contexto de prueba del sitio.',
            'attribution' => [
                'oppref' => 'openai-abc',
                'utm_campaign' => 'black-friday',
            ],
        ])->assertOk();

        $this->assertSame(
            'Hola',
            SiteAssistantMessage::withoutGlobalScopes()
                ->where('session_key', 'attr-session')
                ->where('role', 'visitor')
                ->value('body'),
        );

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Seguimos',
            'session_key' => 'attr-session',
            'attribution' => [
                'oppref' => 'other-pref',
                'utm_source' => 'newsletter',
            ],
        ])->assertOk();

        $meta = AutomationFlowSession::query()
            ->where('external_key', 'attr-session')
            ->first()
            ?->meta;

        $this->assertSame('openai-abc', data_get($meta, 'attribution.oppref'));
        $this->assertSame('black-friday', data_get($meta, 'attribution.utm_campaign'));
        $this->assertSame('newsletter', data_get($meta, 'attribution.utm_source'));
    }

    public function test_embed_prompt_key_pins_session_without_changing_team_default(): void
    {
        [$team, $automation] = $this->webAssistant();
        $automation->forceFill(['entry_prompt_key' => 'contacts:landing'])->save();

        $module = Module::query()->firstOrCreate(
            ['key' => 'contacts'],
            ['name' => 'Contacts', 'group' => null, 'order' => 0, 'status' => 1],
        );
        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'web_revision',
            'section_label' => 'Web revision',
            'prompt_instruction' => 'Prompt solo para la web.',
            'is_active' => true,
            'order' => 0,
        ]);

        $this->mock(AssistantChatService::class, function ($mock): void
        {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(function (...$args): bool
                {
                    return ($args[5] ?? null) === 'web_revision';
                })
                ->andReturn([
                    'response' => 'Desde web_revision',
                    'routed_to' => 'web_revision',
                ]);
        });

        $this->postJson(route('api.embed.automation.assistant', $automation->public_token), [
            'message' => 'Hola web',
            'session_key' => 'prompt-pin-session',
            'prompt_key' => 'web_revision',
        ])
            ->assertOk()
            ->assertJsonPath('reply', 'Desde web_revision');

        $this->assertSame(
            'web_revision',
            data_get(
                AutomationFlowSession::query()->where('external_key', 'prompt-pin-session')->first()?->meta,
                'inbound_prompt_key',
            ),
        );
        $this->assertSame('contacts:landing', $automation->fresh()->entry_prompt_key);
    }

    public function test_identify_writes_oppref_first_touch_on_contact_data(): void
    {
        [$team, $automation] = $this->webAssistant();

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'lead.oppref@example.com',
            'name' => 'Lead Pref',
            'session_key' => 'identify-oppref',
            'attribution' => [
                'oppref' => 'pref-lead-1',
                'utm_campaign' => 'ads-chatgpt',
            ],
        ])->assertOk();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'lead.oppref@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('pref-lead-1', data_get($contact->data, 'oppref'));
        $this->assertSame('ads-chatgpt', data_get($contact->data, 'attribution.utm_campaign'));

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'lead.oppref@example.com',
            'session_key' => 'identify-oppref-2',
            'attribution' => [
                'oppref' => 'pref-lead-2',
            ],
        ])->assertOk();

        $this->assertSame('pref-lead-1', data_get($contact->fresh()->data, 'oppref'));
    }

    public function test_an_escalar_lead_keeps_the_chatgpt_reference_and_intent(): void
    {
        [$team, $automation] = $this->webAssistant();

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'escalar.lead@example.com',
            'name' => 'Diego Mascarenhas',
            'phone' => '+34 600000000',
            'session_key' => 'escalar-session',
            'landing' => 'escalar',
            'intent' => 'mvp',
            'country' => 'ES',
            'message' => "Quiero lanzar un MVP rápido\n\nLa app ya corre en un preview.",
            'attribution' => [
                'oppref' => 'chatgpt-click-1',
                'utm_source' => 'chatgpt',
                'utm_medium' => 'cpc',
            ],
        ])->assertOk();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'escalar.lead@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame('escalar', data_get($contact->data, 'source'));
        $this->assertSame('mvp', data_get($contact->data, 'intent'));
        $this->assertSame('chatgpt-click-1', data_get($contact->data, 'oppref'));
        $this->assertSame('chatgpt', data_get($contact->data, 'attribution.utm_source'));
        $this->assertSame(724, (int) $contact->country);
        $this->assertStringContainsString('La app ya corre en un preview.', (string) data_get($contact->data, 'notes'));
    }

    public function test_an_escalar_lead_without_a_phone_does_not_get_a_country(): void
    {
        [$team, $automation] = $this->webAssistant();

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'escalar.nophone@example.com',
            'name' => 'Diego Mascarenhas',
            'session_key' => 'escalar-no-phone',
            'landing' => 'escalar',
            'intent' => 'mvp',
            'country' => 'ES',
            'message' => 'Quiero publicarla.',
        ])->assertOk();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'escalar.nophone@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertNull($contact->country);
        $this->assertNull($contact->phone);
        $this->assertStringContainsString('Quiero publicarla.', (string) data_get($contact->data, 'notes'));
    }

    public function test_an_escalar_whatsapp_number_assigns_its_country(): void
    {
        [$team, $automation] = $this->webAssistant();

        $this->postJson(route('api.embed.automation.identify', $automation->public_token), [
            'email' => 'escalar.ar@example.com',
            'name' => 'Chat GPT',
            'phone' => '+54 9 11 4448 8888',
            'session_key' => 'escalar-ar',
            'landing' => 'escalar',
            'intent' => 'mvp',
            'message' => 'Quiero publicarla.',
        ])->assertOk();

        $contact = Contact::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('email', 'escalar.ar@example.com')
            ->first();

        $this->assertNotNull($contact);
        $this->assertSame(32, (int) $contact->getAttribute('country'));
        $this->assertStringContainsString('Quiero publicarla.', (string) data_get($contact->data, 'notes'));
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
