<?php

namespace Tests\Feature\Api;

use App\Enums\PaidAdObjective;
use App\Models\Module;
use App\Models\User;
use App\Services\PaidAds\PaidAdCopySuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidAdCopySuggestionApiTest extends TestCase
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

        return [$user, $team->fresh(), $user->createToken('idoneo-ads-copy')->plainTextToken];
    }

    public function test_suggests_copy_from_goal_context(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->mock(PaidAdCopySuggestionService::class, function ($mock)
        {
            $mock->shouldReceive('suggest')
                ->once()
                ->andReturn([
                    'headline' => 'Tu web, lista en 15 días',
                    'body' => 'Diseñamos sitios que convierten. Pedí una demo.',
                    'interests' => 'diseño web, emprendedores, marketing digital',
                    'age_min' => 25,
                    'age_max' => 45,
                ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-copy', [
                'goal' => 'Quiero más consultas para un estudio de diseño web en Córdoba.',
                'name' => 'Webs Córdoba',
                'objective' => PaidAdObjective::Leads->value,
                'locations' => 'Córdoba, Argentina',
                'platforms' => 'Meta, Google Ads',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.headline', 'Tu web, lista en 15 días')
            ->assertJsonPath('data.age_min', 25)
            ->assertJsonPath('data.age_max', 45);
    }

    public function test_goal_is_required(): void
    {
        [, , $token] = $this->adminWithToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/paid-ads/suggest-copy', [
                'goal' => 'corto',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['goal']);
    }

    public function test_guest_cannot_suggest_copy(): void
    {
        $this->postJson('/api/paid-ads/suggest-copy', [
            'goal' => 'Quiero más consultas para un estudio de diseño web.',
        ])->assertUnauthorized();
    }

    public function test_parse_normalizes_age_range_and_limits(): void
    {
        $service = app(PaidAdCopySuggestionService::class);

        $parsed = $service->parse(<<<'JSON'
```json
{"headline":"Oferta limitada","body":"Reservá hoy.","interests":"viajes","age_min":70,"age_max":12}
```
JSON);

        $this->assertSame('Oferta limitada', $parsed['headline']);
        $this->assertSame(13, $parsed['age_min']);
        $this->assertSame(65, $parsed['age_max']);
    }
}
