<?php

namespace Tests\Feature;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\DefaultAssistantFlowPromptsService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshAssistantPromptsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithPrompts(): Team
    {
        $this->seed(ModuleSeeder::class);
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        DefaultAssistantFlowPromptsService::syncForTeam((int) $team->id);

        return $team;
    }

    private function flowPrompt(int $teamId, string $sectionKey): ?Prompt
    {
        return Prompt::withoutGlobalScope('team')
            ->forTeam($teamId)
            ->where('section_key', $sectionKey)
            ->first();
    }

    public function test_it_overwrites_an_edited_prompt_with_the_shipped_default(): void
    {
        $team = $this->teamWithPrompts();

        $prompt = $this->flowPrompt((int) $team->id, 'assistant_catalogo');
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'TEXTO VIEJO';
        $prompt->save();

        $this->artisan('assistant:refresh-prompts', ['team' => $team->id])
            ->assertSuccessful();

        $prompt->refresh();
        $this->assertNotSame('TEXTO VIEJO', $prompt->prompt_instruction);
        $this->assertStringContainsString('add_to_whatsapp_cart', (string) $prompt->prompt_instruction);
    }

    public function test_it_keeps_the_activation_and_order_the_team_chose(): void
    {
        $team = $this->teamWithPrompts();

        $prompt = $this->flowPrompt((int) $team->id, 'assistant_finanzas');
        $this->assertNotNull($prompt);
        $prompt->is_active = false;
        $prompt->order = 99;
        $prompt->save();

        $this->artisan('assistant:refresh-prompts', ['team' => $team->id])
            ->assertSuccessful();

        $prompt->refresh();
        $this->assertFalse((bool) $prompt->is_active);
        $this->assertSame(99, (int) $prompt->order);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $team = $this->teamWithPrompts();

        $prompt = $this->flowPrompt((int) $team->id, 'assistant_tareas');
        $this->assertNotNull($prompt);
        $prompt->prompt_instruction = 'TEXTO VIEJO';
        $prompt->save();

        $this->artisan('assistant:refresh-prompts', ['team' => $team->id, '--dry-run' => true])
            ->assertSuccessful();

        $prompt->refresh();
        $this->assertSame('TEXTO VIEJO', $prompt->prompt_instruction);
    }

    public function test_it_fails_for_an_unknown_team(): void
    {
        $this->seed(ModuleSeeder::class);

        $this->artisan('assistant:refresh-prompts', ['team' => 999999])
            ->assertFailed();
    }
}
