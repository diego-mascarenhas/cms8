<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithToken(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'templates'],
            [
                'name' => 'Templates',
                'icon' => 'template',
                'description' => 'Templates management module',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $team->enableModule('templates');

        $token = $user->createToken('idoneo-mailer-test')->plainTextToken;

        return [$user, $team, $token];
    }

    public function test_can_create_list_show_update_duplicate_and_delete_template(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/templates', [
                'name' => 'Welcome Email',
                'status_id' => true,
                'html' => '<p>Hello</p>',
                'css' => 'p { color: red; }',
                'editor_json' => ['version' => 1, 'blocks' => []],
            ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Welcome Email')
            ->assertJsonPath('data.html', '<p>Hello</p>')
            ->assertJsonPath('data.css', 'p { color: red; }')
            ->assertJsonPath('data.editor_json.version', 1)
            ->assertJsonPath('data.status_id', true);

        $templateId = $create->json('data.id');
        $this->assertNotNull($templateId);
        $this->assertNotEmpty($create->json('data.hashed_id'));
        $this->assertDatabaseHas('templates', [
            'id' => $templateId,
            'team_id' => $team->id,
            'name' => 'Welcome Email',
        ]);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/templates?search=Welcome');

        $list->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($list->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Welcome Email'));

        $show = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/templates/'.$templateId);

        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Welcome Email')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'status_id',
                    'html',
                    'css',
                    'editor_json',
                    'hashed_id',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $update = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/templates/'.$templateId, [
                'name' => 'Welcome Email Updated',
                'html' => '<p>Updated</p>',
                'editor_json' => ['version' => 2],
            ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Welcome Email Updated')
            ->assertJsonPath('data.html', '<p>Updated</p>')
            ->assertJsonPath('data.css', 'p { color: red; }')
            ->assertJsonPath('data.editor_json.version', 2);

        $duplicate = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/templates/'.$templateId.'/duplicate');

        $duplicate->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Welcome Email Updated (copy)')
            ->assertJsonPath('data.html', '<p>Updated</p>');

        $copyId = $duplicate->json('data.id');
        $this->assertNotSame($templateId, $copyId);

        $delete = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/templates/'.$templateId);

        $delete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('templates', ['id' => $templateId]);
    }

    public function test_templates_auto_enable_templates_module(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        Module::query()->firstOrCreate(
            ['key' => 'templates'],
            [
                'name' => 'Templates',
                'icon' => 'template',
                'description' => 'Templates management module',
                'is_core' => false,
                'status' => 1,
            ],
        );

        $this->assertFalse($team->fresh()->hasModule('templates'));

        $token = $user->createToken('no-module')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/templates')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($team->fresh()->hasModule('templates'));
    }

    public function test_templates_forbidden_when_catalog_module_missing(): void
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        $token = $user->createToken('no-catalog')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/templates')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertFalse($team->fresh()->hasModule('templates'));
    }

    public function test_unauthenticated_cannot_access_templates(): void
    {
        $this->getJson('/api/templates')->assertUnauthorized();
        $this->postJson('/api/templates', [])->assertUnauthorized();
    }

    public function test_update_preserves_unrelated_gjs_data_keys(): void
    {
        [, $team, $token] = $this->adminWithToken();

        $template = Template::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Preserve Keys',
            'status_id' => true,
            'gjs_data' => [
                'html' => '<p>A</p>',
                'css' => 'a{}',
                'components' => [['type' => 'text']],
                'styles' => [['selectors' => ['p']]],
                'editor_json' => ['v' => 1],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/templates/'.$template->id, [
                'html' => '<p>B</p>',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.html', '<p>B</p>')
            ->assertJsonPath('data.css', 'a{}')
            ->assertJsonPath('data.editor_json.v', 1);

        $template->refresh();
        $this->assertSame([['type' => 'text']], $template->gjs_data['components']);
        $this->assertSame([['selectors' => ['p']]], $template->gjs_data['styles']);
    }
}
