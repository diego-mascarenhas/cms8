<?php

namespace Tests\Unit;

use App\Models\Template;
use App\Models\User;
use App\Services\MessageFormTemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageFormTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_template_when_team_has_none(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;

        $resolver = app(MessageFormTemplateResolver::class);
        $template = $resolver->resolveForForm(null, $teamId);

        $this->assertInstanceOf(Template::class, $template);
        $this->assertSame(1, Template::withoutGlobalScopes()->where('team_id', $teamId)->count());
        $this->assertNotEmpty($template->gjs_data['html'] ?? null);
    }

    public function test_it_reuses_existing_template_instead_of_creating_duplicates(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $teamId = (int) $user->currentTeam->id;

        $existing = Template::withoutGlobalScopes()->create([
            'name' => 'Existing',
            'team_id' => $teamId,
            'status_id' => 1,
            'gjs_data' => ['html' => '<body>x</body>', 'css' => '', 'styles' => '[]', 'components' => '[]'],
        ]);

        $resolver = app(MessageFormTemplateResolver::class);
        $resolved = $resolver->resolveForForm(null, $teamId);

        $this->assertSame($existing->id, $resolved->id);
        $this->assertSame(1, Template::withoutGlobalScopes()->where('team_id', $teamId)->count());
    }
}
