<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Team;
use App\Services\TeamSiteAssistantPromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSiteAssistantEmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_embed_resolves_team_slug_without_auth(): void
    {
        $team = Team::factory()->create(['name' => "Humano's Team"]);
        $automation = Automation::factory()->create([
            'team_id' => $team->id,
            'slug' => TeamSiteAssistantPromptService::EMBED_SLUG,
            'is_active' => true,
            'channels' => Automation::normalizeChannels(['api' => true]),
            'settings' => ['welcome_message' => 'Hola desde la web'],
        ]);

        $response = $this->getJson('/api/embed/site/humanos-team')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.embed.name', "Humano's Team")
            ->assertJsonPath('data.embed.welcome_message', 'Hola desde la web');

        $apiBase = $response->json('data.embed.api_base');
        $this->assertIsString($apiBase);
        $this->assertStringContainsString('/api/embed/automation/'.$automation->public_token, $apiBase);
    }

    public function test_unknown_slug_returns_not_found(): void
    {
        $this->getJson('/api/embed/site/no-existe')->assertNotFound();
    }

    public function test_team_without_web_assistant_returns_not_found(): void
    {
        Team::factory()->create(['name' => 'Sin Embed']);

        $this->getJson('/api/embed/site/'.Str::slug('Sin Embed'))->assertNotFound();
    }
}
