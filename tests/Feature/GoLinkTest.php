<?php

namespace Tests\Feature;

use App\Jobs\RecordGoLinkHitJob;
use App\Models\GoLink;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use App\Services\Go\GoLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        config(['services.go.url' => 'https://go.idoneo.dev']);
        config(['services.shop.url' => 'https://pedimosfacil.com']);
    }

    /**
     * @return array{0: User, 1: Team, 2: string}
     */
    private function adminWithShop(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['products', 'stores', 'orders'] as $key)
        {
            Module::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => ucfirst($key),
                    'icon' => 'shopping-cart',
                    'description' => $key,
                    'is_core' => false,
                    'status' => 1,
                ],
            );
        }

        $this->enableTeamModules($team, ['products', 'stores', 'orders']);

        $team->setSetting('business_config', [
            'business_name' => 'Wilfredo Demo',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting('public_catalog_enabled', true, [
            'group' => 'public_shop',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        $token = $user->createToken('go-link-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }

    public function test_ensure_catalog_qr_creates_stable_go_link(): void
    {
        [, $team, $token] = $this->adminWithShop();

        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/go-links/catalog-qr')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertNotEmpty($first['code']);
        $this->assertSame('https://go.idoneo.dev/q/'.$first['code'], $first['url']);
        $this->assertStringContainsString('pedimosfacil.com', $first['target_url']);

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/go-links/catalog-qr')
            ->assertOk()
            ->json('data');

        $this->assertSame($first['code'], $second['code']);
        $this->assertDatabaseCount('go_links', 1);
        $this->assertDatabaseHas('go_links', [
            'team_id' => $team->id,
            'type' => GoLink::TYPE_SHOP_CATALOG_QR,
            'code' => $first['code'],
        ]);
    }

    public function test_qr_redirect_dispatches_hit_job_and_redirects(): void
    {
        Bus::fake([RecordGoLinkHitJob::class]);

        $link = GoLink::factory()->create([
            'target_url' => 'https://pedimosfacil.com/wilfredo',
            'code' => 'abc23456',
            'active' => true,
        ]);

        $this->get('/q/'.$link->code)
            ->assertRedirect('https://pedimosfacil.com/wilfredo');

        Bus::assertDispatched(RecordGoLinkHitJob::class, function (RecordGoLinkHitJob $job) use ($link): bool
        {
            return $job->goLinkId === $link->id;
        });
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->get('/q/doesnot1')->assertNotFound();
    }

    public function test_record_hit_job_persists_event(): void
    {
        $link = GoLink::factory()->create(['hits_count' => 0]);

        (new RecordGoLinkHitJob($link->id, [
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'user_agent' => 'PHPUnit',
            'referer' => null,
        ]))->handle();

        $this->assertDatabaseHas('go_link_hits', [
            'go_link_id' => $link->id,
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertSame(1, $link->fresh()->hits_count);
        $this->assertNotNull($link->fresh()->last_hit_at);
    }

    public function test_ensure_requires_catalog_slug(): void
    {
        [, , $token] = $this->adminWithShop();

        $this->mock(GoLinkService::class, function ($mock)
        {
            $mock->shouldReceive('ensureShopCatalogQr')->once()->andReturn(null);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/go-links/catalog-qr')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
