<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateEditorReturnRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_editor_includes_return_patch_script_when_return_url_is_valid(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user->fresh());

        $template = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'Return patch tpl',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>x</p>'],
        ]);

        $returnPath = '/message/list';
        $response = $this->get(route('template.editor', $template->getHashedId()).'?'.http_build_query([
            'return_url' => $returnPath,
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('window.grapesJsToolbarLabels', $html);
        $this->assertStringContainsString('runOpenBlocksOnce', $html);
        $this->assertStringContainsString('var returnUrl = ', $html);
        $this->assertStringContainsString('goReturn', $html);
        $this->assertStringContainsString('blurCanvasFocus', $html);
        $this->assertStringContainsString('flushCkeditorInCanvas', $html);
        $this->assertStringContainsString('runStoreAfterFlush', $html);
        $this->assertStringContainsString('requestAnimationFrame', $html);
    }

    public function test_template_editor_omits_return_script_for_untrusted_return_url(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user->fresh());

        $template = Template::create([
            'team_id' => (int) $team->id,
            'name' => 'No return tpl',
            'status_id' => true,
            'gjs_data' => ['html' => '<p>y</p>'],
        ]);

        $response = $this->get(route('template.editor', $template->getHashedId()).'?'.http_build_query([
            'return_url' => 'https://evil.example/phish',
        ]));

        $response->assertOk();
        $html = $response->getContent() ?? '';
        $this->assertStringContainsString('window.grapesJsToolbarLabels', $html);
        $this->assertStringContainsString('runOpenBlocksOnce', $html);
        $this->assertStringNotContainsString('goReturn', $html);
    }
}
