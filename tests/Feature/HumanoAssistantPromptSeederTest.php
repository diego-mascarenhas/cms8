<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanoAssistantPromptSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_intent_config_includes_humano_assistant_routing_key(): void
    {
        $intent = config('assistant_tool_intent_prompts.intents.humano_assistant');
        $this->assertIsArray($intent);
        $this->assertContains('products:humano_assistant', $intent['routing_keys']);
    }

    public function test_prompt_seeder_creates_humano_assistant_sales_prompt_for_default_team(): void
    {
        $this->seed(ModuleSeeder::class);
        Team::factory()->create();

        $this->seed(PromptSeeder::class);

        $productsModule = Module::where('key', 'products')->first();
        $this->assertNotNull($productsModule);

        $teamId = (int) Team::query()->min('id');
        $prompt = Prompt::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('module_id', $productsModule->id)
            ->where('section_key', 'humano_assistant')
            ->first();

        $this->assertNotNull($prompt);
        $this->assertSame('Assistant — venta y demo', $prompt->section_label);
        $this->assertStringContainsString('search_contacts', $prompt->prompt_instruction);
        $this->assertStringContainsString('48 horas', $prompt->prompt_instruction);
        $this->assertStringContainsString('tokens', mb_strtolower($prompt->prompt_instruction));
        $this->assertStringContainsString('buy.stripe.com/5kQ4gzacZ3Nk9HM0Qd43S07', $prompt->prompt_instruction);
        $this->assertStringContainsString('49 €', $prompt->prompt_instruction);
        $this->assertTrue($prompt->is_active);
    }
}
