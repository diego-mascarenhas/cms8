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
        $this->assertSame('Pedido de presupuesto', $presupuesto->section_label);
        $this->assertStringContainsString('Consultoría de negocio o técnica', (string) $presupuesto->prompt_instruction);
        $this->assertStringContainsString('Nunca IDONEO', (string) $presupuesto->prompt_instruction);

        $catalogo = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_catalogo')
            ->first();
        $this->assertNotNull($catalogo);
        $this->assertSame('Venta desde la tienda', $catalogo->section_label);

        $embudo = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_embudo')
            ->first();
        $this->assertNotNull($embudo);
        $this->assertSame('Embudo comercial', $embudo->section_label);

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
}
