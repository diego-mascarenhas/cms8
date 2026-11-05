<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAssistantFlowPromptsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_default_assistant_flow_prompts_for_team(): void
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $citas = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_citas')
            ->first();
        $this->assertNotNull($citas);
        $this->assertSame('Agendar una cita', $citas->section_label);

        $presupuesto = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_presupuesto')
            ->first();
        $this->assertNotNull($presupuesto);
        $this->assertSame('Shop: cotizar una lista', $presupuesto->section_label);
        $this->assertStringContainsString('Consultoría de negocio o técnica', (string) $presupuesto->prompt_instruction);
        $this->assertStringContainsString('Nunca IDONEO', (string) $presupuesto->prompt_instruction);
        $this->assertStringContainsString('create_shop_order', (string) $presupuesto->prompt_instruction);

        $catalogo = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_catalogo')
            ->first();
        $this->assertNotNull($catalogo);
        $this->assertSame('Shop: vender del catálogo', $catalogo->section_label);

        $embudo = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_embudo')
            ->first();
        $this->assertNotNull($embudo);
        $this->assertSame('WhatsApp: calificar y vender', $embudo->section_label);

        $contactos = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_contactos')
            ->first();
        $this->assertNotNull($contactos);
        $this->assertSame('Contactos y categorías', $contactos->section_label);

        $tareas = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_tareas')
            ->first();
        $this->assertNotNull($tareas);
        $this->assertSame('Tareas y equipo', $tareas->section_label);

        $campanas = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_campanas')
            ->first();
        $this->assertNotNull($campanas);
        $this->assertSame('Campañas y News', $campanas->section_label);

        $hosting = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_hosting')
            ->first();
        $this->assertNotNull($hosting);
        $this->assertSame('Hosting cPanel', $hosting->section_label);
        $this->assertStringContainsString('reset_cpanel_password', (string) $hosting->prompt_instruction);
        $this->assertStringContainsString('varios dominios', (string) $hosting->prompt_instruction);
        $this->assertStringContainsString('hosting_google_mail', (string) $hosting->prompt_instruction);
        $this->assertStringContainsString('include:spf.revisionalpha.com', (string) $hosting->prompt_instruction);
        $this->assertStringContainsString('hosting_product_info', (string) $hosting->prompt_instruction);

        $this->assertNull(
            \App\Models\Prompt::withoutGlobalScope('team')
                ->forTeam((int) $team->id)
                ->where('section_key', 'assistant_etiquetado')
                ->first(),
        );

        $list60First = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'primer_contacto')
            ->first();
        $this->assertNotNull($list60First);
        $this->assertStringContainsString('corto, humano', (string) $list60First->prompt_instruction);

        $list60FollowUp = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'seguimiento')
            ->first();
        $this->assertNotNull($list60FollowUp);

        $list60Alta = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'alta')
            ->first();
        $this->assertNotNull($list60Alta);
        $this->assertStringContainsString('alta desde el inbox', (string) $list60Alta->prompt_instruction);
    }

    public function test_sync_does_not_overwrite_custom_instruction(): void
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $prompt = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_tareas')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'INSTRUCCIÓN PERSONALIZADA';
        $prompt->save();

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        $prompt->refresh();
        $this->assertSame('INSTRUCCIÓN PERSONALIZADA', $prompt->prompt_instruction);
    }

    public function test_sync_renames_the_old_shop_labels_and_keeps_a_custom_one(): void
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $prompt = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_presupuesto')
            ->first();
        $this->assertNotNull($prompt);
        $prompt->section_label = 'Pedido de presupuesto';
        $prompt->save();

        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $this->assertSame('Shop: cotizar una lista', $prompt->fresh()->section_label);

        $prompt->section_label = 'Mi cotizador';
        $prompt->save();
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);
        $this->assertSame('Mi cotizador', $prompt->fresh()->section_label);
    }
}
