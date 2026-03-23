<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WapifyMePromptSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_intent_config_includes_wapify_routing_key(): void
    {
        $intent = config('assistant_tool_intent_prompts.intents.wapify');
        $this->assertIsArray($intent);
        $this->assertContains('products:wapify_me', $intent['routing_keys']);
    }

    public function test_prompt_seeder_creates_wapify_me_prompt_for_default_team(): void
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
            ->where('section_key', 'wapify_me')
            ->first();

        $this->assertNotNull($prompt);
        $this->assertStringContainsString('buy.stripe.com', $prompt->prompt_instruction);
        $this->assertStringContainsString('wapify.me', $prompt->prompt_instruction);
        $this->assertStringContainsString('wapify.me/launch', $prompt->prompt_instruction);
        $this->assertStringContainsString('wapify.me/demo', $prompt->prompt_instruction);
        $this->assertStringContainsString('LANZAMIENTOWAPIFY', $prompt->prompt_instruction);
        $this->assertStringContainsString('PEDIMOSFACIL', $prompt->prompt_instruction);
        $this->assertTrue($prompt->is_active);
    }
}
