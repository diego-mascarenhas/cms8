<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateShopBriefOrderJob;
use App\Models\Module;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Services\EstimatorProductQuoteService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShopBriefOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(CurrencySeeder::class);
    }

    public function test_a_pasted_list_becomes_a_shop_order(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'ASPARAGUS GREEN FRESH',
            'code' => '000107',
            'price' => 272,
            'cost' => null,
            'markup_percent' => null,
            'status' => false,
            'catalog_status' => \App\Enums\ProductCatalogStatus::Draft,
        ]);

        $this->partialMock(EstimatorProductQuoteService::class, function ($mock): void
        {
            $mock->shouldReceive('quote')->once()->andReturnUsing(function (string $brief, $team): array
            {
                return (new EstimatorProductQuoteService)->present($team, [
                    'title' => 'Pedido',
                    'summary' => 'Tres espárragos.',
                    'lines' => [
                        [
                            'name' => 'Espárragos verdes frescos',
                            'code' => 'ASPARAGUS GREEN FRESH',
                            'quantity' => 3,
                            'unit' => 'u',
                            'detail' => '',
                        ],
                        [
                            'name' => 'PLUM FRESH',
                            'code' => '',
                            'quantity' => 1,
                            'unit' => 'u',
                            'detail' => '',
                        ],
                    ],
                ], $brief);
            });
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/orders/from-brief', [
                'brief' => '3 ASPARAGUS GREEN FRESH y PLUM FRESH',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'processing');

        $pollToken = (string) $response->json('poll_token');
        $state = Cache::get(GenerateShopBriefOrderJob::cacheKey($pollToken));
        $this->assertSame('ready', $state['status'] ?? null);

        $order = Order::withoutGlobalScopes()->find($state['order_id'] ?? 0);
        $this->assertNotNull($order);
        $this->assertSame((int) $team->id, (int) $order->team_id);
        $this->assertStringStartsWith('PED-', (string) $order->order_number);
        $this->assertSame(816.0, (float) $order->total_amount);
        $items = $order->metadata['items'] ?? [];
        $this->assertSame('ASPARAGUS GREEN FRESH', $items[0]['name'] ?? null);
        $this->assertSame('PLUM FRESH', $items[1]['name'] ?? null);
        $this->assertSame(0.0, (float) ($items[1]['unit_price'] ?? -1));
        $this->assertSame($order->id, $state['order_id'] ?? null);
    }

    public function test_a_quote_failure_stays_on_the_same_team(): void
    {
        [, $team, $token] = $this->adminWithShopModules();
        $this->partialMock(EstimatorProductQuoteService::class, function ($mock): void
        {
            $mock->shouldReceive('quote')->once()->andThrow(new \ErrorException('Array to string conversion'));
        });

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/orders/from-brief', [
                'brief' => '3 ASPARAGUS GREEN FRESH',
            ]);

        $response->assertOk();
        $state = Cache::get(GenerateShopBriefOrderJob::cacheKey((string) $response->json('poll_token')));
        $this->assertSame('failed', $state['status'] ?? null);
        $this->assertSame((int) $team->id, (int) ($state['team_id'] ?? 0));
    }

    public function test_whatsapp_creates_a_shop_order_from_the_written_names(): void
    {
        [, $team] = $this->adminWithShopModules();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'ACEITE CAÑUELAS X 1,5 L - UNIDAD',
            'code' => '7792180001665',
            'price' => 5488,
            'cost' => null,
            'markup_percent' => null,
        ]);

        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext((int) $team->user_id, (int) $team->id, '5491112345678');

        $out = $tools->execute('create_shop_order', [
            'products' => [
                ['name' => 'ACEITE CAÑUELAS X 1,5 L - UNIDAD', 'quantity' => 2],
            ],
        ]);

        $this->assertStringContainsString('WA-', $out);
        $this->assertStringContainsString('5.488,00 ARS', $out);

        $order = Order::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('whatsapp', $order->metadata['source'] ?? null);
        $this->assertSame('5491112345678', $order->metadata['phone'] ?? null);
        $this->assertSame(10976.0, (float) $order->total_amount);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
     */
    private function adminWithShopModules(): array
    {
        if (! Features::hasTeamFeatures())
        {
            $this->markTestSkipped('Jetstream team features disabled.');
        }

        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->assignRole('admin');

        foreach (['products', 'orders'] as $key)
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

        $this->enableTeamModules($team, ['products', 'orders']);
        $token = $user->createToken('idoneo-shop-test')->plainTextToken;

        return [$user->fresh(), $team->fresh(), $token];
    }
}
