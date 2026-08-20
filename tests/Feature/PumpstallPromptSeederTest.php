<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumpstallPromptSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_intent_config_includes_pumpstall_routing_key(): void
    {
        $intent = config('assistant_tool_intent_prompts.intents.pumpstall');
        $this->assertIsArray($intent);
        $this->assertContains('products:pumpstall', $intent['routing_keys']);
    }

    public function test_prompt_seeder_creates_pumpstall_sales_prompt_for_default_team(): void
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
            ->where('section_key', 'pumpstall')
            ->first();

        $this->assertNotNull($prompt);
        $this->assertSame('Pumpstall — venta y traders', $prompt->section_label);
        $this->assertStringContainsString('search_contacts', $prompt->prompt_instruction);
        $this->assertStringContainsString('https://t.me/pumpstall', $prompt->prompt_instruction);
        $this->assertStringContainsString('https://pumpstall.com', $prompt->prompt_instruction);
        $this->assertStringContainsString('https://pumpstall.com/help', $prompt->prompt_instruction);
        $this->assertStringContainsString('149 USD', $prompt->prompt_instruction);
        $this->assertStringContainsString('FOUNDERS', $prompt->prompt_instruction);
        $this->assertStringContainsString('buy.stripe.com/4gMcN70Ku58O2aW7wD1B607', $prompt->prompt_instruction);
        $this->assertTrue($prompt->is_active);
    }
}
