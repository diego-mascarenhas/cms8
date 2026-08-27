<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantAutomationRunner;
use App\Services\AssistantToolIntentPromptService;
use App\Services\TeamSiteAssistantPromptService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSiteAssistantPromptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSeeder::class);
    }

    public function test_default_instruction_covers_appointments_catalog_and_sales(): void
    {
        $instruction = app(TeamSiteAssistantPromptService::class)->defaultInstruction();

        $this->assertStringContainsString('citas', mb_strtolower($instruction));
        $this->assertStringContainsString('catálogo', mb_strtolower($instruction));
        $this->assertStringContainsString('create_calendar_event', $instruction);
        $this->assertStringContainsString('no cancela la cita', $instruction);
        $this->assertStringContainsString('quien la pide', $instruction);
        $this->assertStringContainsString('nombre, apellido y email', $instruction);
        $this->assertStringContainsString('list_product_catalog', $instruction);
        $this->assertStringContainsString('get_store_info', $instruction);
        $this->assertStringContainsString('add_to_whatsapp_cart', $instruction);
        $this->assertStringContainsString('send_product_image', $instruction);
        $this->assertStringContainsString('confirm_whatsapp_order', $instruction);
    }

    public function test_intent_resolution_falls_back_to_site_prompt(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Citas y catálogo.',
            'is_active' => true,
            'order' => 0,
        ]);

        app(TeamSiteAssistantPromptService::class)->select($team, 'chat:citas_y_ventas');

        $resolution = app(AssistantToolIntentPromptService::class)
            ->resolveFlowForToolAssistant((int) $team->id, 'Hola, qué tal', null);

        $this->assertNotNull($resolution['prompt']);
        $this->assertSame('citas_y_ventas', $resolution['prompt']->section_key);
        $this->assertSame('chat:citas_y_ventas', $resolution['routing_key']);
        $this->assertSame('omit', $resolution['persist_assistant_flow_key']);
    }

    public function test_find_default_for_channel_prefers_site_embed_automation(): void
    {
        $team = Team::factory()->create();
        Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => 'otro-flujo',
            'channels' => Automation::normalizeChannels(['api' => true, 'chat' => true]),
        ]);
        $embed = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => TeamSiteAssistantPromptService::EMBED_SLUG,
            'entry_prompt_key' => 'chat:citas_y_ventas',
            'channels' => Automation::normalizeChannels(['api' => true, 'chat' => true]),
        ]);

        $found = app(AssistantAutomationRunner::class)
            ->findDefaultForChannel((int) $team->id, Automation::CHANNEL_API);

        $this->assertNotNull($found);
        $this->assertSame($embed->id, $found->id);
    }

    public function test_embed_snippet_includes_widget_and_public_token(): void
    {
        $team = Team::factory()->create();
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => TeamSiteAssistantPromptService::EMBED_SLUG,
            'public_token' => str_repeat('ab', 32),
            'channels' => Automation::normalizeChannels(['api' => true]),
        ]);

        $snippet = app(TeamSiteAssistantPromptService::class)->embedSnippet($automation);

        $this->assertNotNull($snippet);
        $this->assertStringContainsString('data-cms8-widget="assistant"', $snippet);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $snippet);
        $this->assertStringContainsString('/api/embed/automation/'.$automation->public_token, $snippet);
        $this->assertStringContainsString('/js/cms8-widgets.js', $snippet);
        $this->assertStringNotContainsString('HUMANO', $snippet);
    }

    public function test_settings_payload_always_creates_cms8_embed_snippet(): void
    {
        $team = Team::factory()->create();

        $payload = app(TeamSiteAssistantPromptService::class)->settingsPayload($team);

        $this->assertNotSame('', $payload['embed']['snippet']);
        $this->assertStringContainsString('data-cms8-widget="assistant"', $payload['embed']['snippet']);
        $this->assertStringContainsString('CMS8_WIDGETS_API_BASE', $payload['embed']['snippet']);
        $this->assertStringContainsString('/js/cms8-widgets.js', $payload['embed']['snippet']);
        $this->assertStringNotContainsString('HUMANO', $payload['embed']['snippet']);
        $this->assertStringContainsString('/js/cms8-widgets.js', $payload['embed']['script_url']);

        $automation = Automation::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('slug', TeamSiteAssistantPromptService::EMBED_SLUG)
            ->first();
        $this->assertNotNull($automation);
        $this->assertNotSame('', (string) $automation->public_token);
        $this->assertStringContainsString($automation->public_token, $payload['embed']['snippet']);
    }

    public function test_update_content_changes_label_and_instruction(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'citas_y_ventas',
            'section_label' => 'Citas y ventas',
            'prompt_instruction' => 'Texto viejo.',
            'is_active' => true,
            'order' => 0,
        ]);

        $service = app(TeamSiteAssistantPromptService::class);
        $service->updateContent($team, 'chat:citas_y_ventas', 'Agenda y tienda', 'Texto nuevo.');

        $option = collect($service->promptOptions($team))->firstWhere('key', 'chat:citas_y_ventas');
        $this->assertNotNull($option);
        $this->assertSame('Agenda y tienda', $option['section_label']);
        $this->assertSame('Texto nuevo.', $option['prompt_instruction']);
        $this->assertSame('customer', $option['audience']);
        $this->assertSame('Para el cliente', $option['audience_label']);
        $this->assertSame(3, $option['audience_rank']);
        $this->assertNull($team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY));
    }

    public function test_update_content_does_not_change_the_selected_site_prompt(): void
    {
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'chat')->first();
        $this->assertNotNull($module);
        $service = app(TeamSiteAssistantPromptService::class);
        $service->select($team, TeamSiteAssistantPromptService::OFF_KEY);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'otro_flujo',
            'section_label' => 'Otro flujo',
            'prompt_instruction' => 'Texto viejo.',
            'is_active' => true,
            'order' => 1,
        ]);

        $service->updateContent($team, 'chat:otro_flujo', 'Otro flujo', 'Texto nuevo.');

        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
    }

    public function test_create_does_not_change_the_selected_site_prompt(): void
    {
        $team = Team::factory()->create();
        $service = app(TeamSiteAssistantPromptService::class);
        $service->select($team, TeamSiteAssistantPromptService::OFF_KEY);

        $service->create($team, 'Bienvenida', 'Hola, soy el asistente.');

        $this->assertSame(
            TeamSiteAssistantPromptService::OFF_KEY,
            $team->fresh()->getSetting(TeamSiteAssistantPromptService::SETTING_KEY),
        );
        $this->assertNotNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_label', 'Bienvenida')
                ->first(),
        );
    }

    public function test_prompt_options_omit_own_brand_sales_scripts(): void
    {
        $team = Team::factory()->create();
        $module = Module::query()->where('key', 'products')->first();
        $this->assertNotNull($module);

        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'wapify_me',
            'section_label' => 'Wapify.Me — venta y suscripción',
            'prompt_instruction' => 'Vendés Wapify.',
            'is_active' => true,
            'order' => 0,
        ]);
        Prompt::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'module_id' => $module->id,
            'section_key' => 'pumpstall',
            'section_label' => 'Pumpstall — venta y traders',
            'prompt_instruction' => 'Vendés Pumpstall.',
            'is_active' => true,
            'order' => 0,
        ]);

        $keys = collect(app(TeamSiteAssistantPromptService::class)->promptOptions($team))->pluck('key');
        $this->assertTrue($keys->contains(fn (string $key): bool => str_ends_with($key, ':assistant_catalogo')));
        $this->assertFalse($keys->contains('products:wapify_me'));
        $this->assertFalse($keys->contains('products:pumpstall'));
    }

    public function test_select_off_is_a_silent_default_without_a_routing_key(): void
    {
        $team = Team::factory()->create();
        $service = app(TeamSiteAssistantPromptService::class);

        $service->select($team, TeamSiteAssistantPromptService::OFF_KEY);

        $this->assertTrue($service->isSilentDefault($team->fresh()));
        $this->assertSame(TeamSiteAssistantPromptService::OFF_KEY, $service->selectedRoutingKey($team->fresh()));
        $this->assertNull($service->resolvedRoutingKey($team->fresh()));
    }

    public function test_select_force_off_pauses_every_chat_without_a_routing_key(): void
    {
        $team = Team::factory()->create();
        $service = app(TeamSiteAssistantPromptService::class);

        $service->select($team, TeamSiteAssistantPromptService::FORCE_OFF_KEY);

        $this->assertFalse($service->isSilentDefault($team->fresh()));
        $this->assertTrue($service->isForceSilent($team->fresh()));
        $this->assertSame(TeamSiteAssistantPromptService::FORCE_OFF_KEY, $service->selectedRoutingKey($team->fresh()));
        $this->assertNull($service->resolvedRoutingKey($team->fresh()));
    }

    public function test_select_copies_a_catalog_quote_prompt_when_the_team_has_no_row(): void
    {
        $team = Team::factory()->create();
        $service = app(TeamSiteAssistantPromptService::class);

        Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_presupuesto')
            ->delete();

        $service->select($team, 'chat:assistant_presupuesto');

        $this->assertSame('chat:assistant_presupuesto', $service->selectedRoutingKey($team->fresh()));
        $this->assertNotNull(
            Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'assistant_presupuesto')
                ->first(),
        );
    }
}
