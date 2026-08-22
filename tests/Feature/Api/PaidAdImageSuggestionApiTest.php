<?php

namespace Tests\Feature\Api;

use App\Enums\PaidAdObjective;
use App\Models\Module;
use App\Models\User;
use App\Services\PaidAds\PaidAdImageSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdImageSuggestionApiTest extends TestCase
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
            ['key' => 'paid_ads'],
            [
                'name' => 'Paid Ads',
                'icon' => 'target-arrow',
                'description' => 'Paid advertising campaigns',
                'is_core' => false,
                'status' => 1,
            ],
        );
        $team->enableModule('paid_ads');

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-image')->plainTextToken];
    }

    public function test_suggests_image_from_campaign_copy(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->mock(PaidAdImageSuggestionService::class, function ($mock)
        {
            $mock->shouldReceive('suggest')
                ->once()
                ->andReturn([
                    'hook' => 'WhatsApp que ya no se atiende a mano',
                    'scene' => 'Dueña de pyme sonriendo con el celular en la mano.',
                    'framing' => 'Vertical, sujeto a la izquierda.',
                    'palette' => ['#0D9488', '#F4EFE6', '#102428'],
                    'avoid' => 'No uses capturas pixeladas.',
                    'query' => 'woman smiling smartphone office',
                    'search' => [
                        'google' => 'https://www.google.com/search?udm=2&tbm=isch&q=woman',
                        'unsplash' => 'https://unsplash.com/s/photos/woman',
                    ],
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-image', [
                'name' => 'Assistant',
                'headline' => 'Automatizá tu WhatsApp',
                'body' => 'Respondé más rápido y no pierdas clientes.',
                'objective' => PaidAdObjective::Traffic->value,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hook', 'WhatsApp que ya no se atiende a mano')
            ->assertJsonPath('data.palette.0', '#0D9488');
    }

    public function test_context_is_required(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-image', [
                'headline' => 'Hola',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['headline']);
    }

    public function test_guest_cannot_suggest_image(): void
    {
        $this->postJson('/api/paid-ads/suggest-image', [
            'headline' => 'Automatizá tu WhatsApp',
        ])->assertUnauthorized();
    }

    public function test_parse_fills_defaults_and_limits_palette(): void
    {
        $service = app(PaidAdImageSuggestionService::class);

        $parsed = $service->parse(<<<'JSON'
```json
{"hook":"Oficina en calma","scene":"Una mesa con un celular y un café.","framing":"","palette":["teal","#abc","#112233","#445566"],"avoid":""}
```
JSON);

        $this->assertSame('Oficina en calma', $parsed['hook']);
        $this->assertSame('Una mesa con un celular y un café.', $parsed['scene']);
        $this->assertNotSame('', $parsed['framing']);
        $this->assertSame(['#112233', '#445566'], $parsed['palette']);
        $this->assertNotSame('', $parsed['avoid']);
        $this->assertSame('Oficina en calma', $parsed['query']);
        $this->assertStringContainsString('google.com', $parsed['search']['google']);
        $this->assertStringContainsString('unsplash.com', $parsed['search']['unsplash']);
    }
}
