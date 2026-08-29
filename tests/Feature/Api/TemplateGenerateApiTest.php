<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\User;
use App\Services\Mail\MailerTemplateGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TemplateGenerateApiTest extends TestCase
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

        return [$user, $team->fresh(), $user->createToken('idoneo-mailer-generate')->plainTextToken];
    }

    public function test_generates_template_from_prompt(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->mock(MailerTemplateGenerationService::class, function ($mock)
        {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'name' => 'Bienvenida',
                    'html' => '<h2>Hola {{name}}</h2><p>Gracias por sumarte.</p>',
                    'css' => '',
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/templates/generate', [
                'prompt' => 'Email de bienvenida cercano para un contacto nuevo.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bienvenida')
            ->assertJsonPath('data.html', '<h2>Hola {{name}}</h2><p>Gracias por sumarte.</p>');
    }

    public function test_prompt_is_required(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/templates/generate', [
                'prompt' => 'corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    public function test_guest_cannot_generate_template(): void
    {
        $this->postJson('/api/templates/generate', [
            'prompt' => 'Email de bienvenida cercano para un contacto nuevo.',
        ])->assertUnauthorized();
    }

    public function test_parse_extracts_body_and_strips_scripts(): void
    {
        $service = app(MailerTemplateGenerationService::class);

        $parsed = $service->parse(<<<'JSON'
```json
{"name":"Promo verano","html":"<html><body><p><img src=\"https://picsum.photos/id/1015/1200/480\" alt=\"Hero\"></p><h2>Oferta</h2><script>alert(1)</script><p>Aprovechá hoy.</p></body></html>","css":"h2{color:red}"}
```
JSON);

        $this->assertSame('Promo verano', $parsed['name']);
        $this->assertSame('<p><img src="https://picsum.photos/id/1015/1200/480" alt="Hero"></p><h2>Oferta</h2><p>Aprovechá hoy.</p>', $parsed['html']);
        $this->assertSame('h2{color:red}', $parsed['css']);
    }
}
