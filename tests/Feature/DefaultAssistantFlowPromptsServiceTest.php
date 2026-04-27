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
        $this->assertStringContainsString('citas', (string) $citas->section_label);

        $contactos = \App\Models\Prompt::withoutGlobalScope('team')
            ->forTeam((int) $team->id)
            ->where('section_key', 'assistant_contactos')
            ->first();
        $this->assertNotNull($contactos);
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
