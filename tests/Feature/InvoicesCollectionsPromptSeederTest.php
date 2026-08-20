<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicesCollectionsPromptSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_intent_config_includes_collections_routing_key(): void
    {
        $intent = config('assistant_tool_intent_prompts.intents.collections_billing');
        $this->assertIsArray($intent);
        $this->assertContains('invoices:collections', $intent['routing_keys']);
    }

    public function test_prompt_seeder_creates_collections_prompt_for_default_team(): void
    {
        $this->seed(ModuleSeeder::class);
        Team::factory()->create();

        $this->seed(PromptSeeder::class);

        $invoicesModule = Module::where('key', 'invoices')->first();
        $this->assertNotNull($invoicesModule);

        $teamId = (int) Team::query()->min('id');
        $prompt = Prompt::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('module_id', $invoicesModule->id)
            ->where('section_key', 'collections')
            ->first();

        $this->assertNotNull($prompt);
        $this->assertSame('Cobranzas', $prompt->section_label);
        $this->assertStringContainsString('search_contacts', $prompt->prompt_instruction);
        $this->assertStringContainsString('invoices', $prompt->prompt_instruction);
        $this->assertStringContainsString('No inventes', $prompt->prompt_instruction);
        $this->assertTrue($prompt->is_active);
    }
}
