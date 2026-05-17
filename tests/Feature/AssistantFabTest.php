<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantFabTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_dashboard_includes_assistant_fab_opening_offcanvas_chat(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $response->assertSee('id="assistant-fab"', false);
        $this->assertStringContainsString('id="assistant-offcanvas"', $html);
        $this->assertStringContainsString('data-bs-toggle="offcanvas"', $html);
        $this->assertStringContainsString('data-bs-target="#assistant-offcanvas"', $html);
        $this->assertStringContainsString('wire:snapshot', $html);
        $this->assertStringNotContainsString('wire:model="respondWithAudio"', $html);
        $this->assertStringNotContainsString('href="'.route('assistant').'"', $html);
        $this->assertStringContainsString('assistant-empty-suggestions', $html);
        $this->assertStringContainsString('assistant-suggestion-example', $html);
        $this->assertStringContainsString('data-prompt=', $html);
        $this->assertStringContainsString('id="assistant-offcanvas-reset-btn"', $html);
    }

    public function test_dedicated_assistant_page_does_not_render_floating_button(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user->fresh())->get(route('assistant'));

        $response->assertOk();
        $response->assertDontSee('id="assistant-fab"', false);
    }

    public function test_full_assistant_page_keeps_chat_header_and_voice_toggle(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->forceFill(['current_team_id' => $user->ownedTeams()->first()->id])->save();

        $response = $this->actingAs($user->fresh())->get(route('assistant'));

        $response->assertOk();
        $this->assertStringContainsString('wire:model="respondWithAudio"', $response->getContent() ?? '');
    }
}
