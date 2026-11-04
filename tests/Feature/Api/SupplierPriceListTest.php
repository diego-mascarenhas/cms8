<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Jetstream\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierPriceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CurrencySeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
        ]);
    }

    /**
     * @return array{0: User, 1: \App\Models\Team, 2: string}
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

        return [$user->fresh(), $team->fresh(), $user->createToken('shop-cost-test')->plainTextToken];
    }

    public function test_supplier_list_sets_cost_and_selling_price(): void
    {
        [, $team, $token] = $this->adminWithShop();
        $currencyId = Currency::query()->where('code', 'ARS')->value('id');

        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 2,
            'name' => 'Textil Norte',
        ]);

        $existing = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Remera vieja',
            'code' => 'REM-001',
            'price' => 10,
            'currency_id' => $currencyId,
            'cost' => 4,
            'markup_percent' => 25,
        ]);

        $csv = implode("\n", [
            'code,name,cost,barcode,oem',
            'REM-001,Remera blanca,1000,,',
            'EMP-012,Empanada de carne,500,,',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/supplier-price-lists/import', [
                'file' => UploadedFile::fake()->createWithContent('lista.csv', $csv),
                'enterprise_id' => $supplier->id,
                'currency_id' => $currencyId,
                'markup_percent' => 50,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $existing->refresh();
        $this->assertEquals(1000, (float) $existing->cost);
        $this->assertEquals(25, (float) $existing->markup_percent);
        $this->assertEquals(1250, (float) $existing->price);

        $draft = Product::query()->where('code', 'EMP-012')->first();
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->catalog_status->value);
        $this->assertEquals(500, (float) $draft->cost);
        $this->assertEquals(50, (float) $draft->markup_percent);
        $this->assertEquals(750, (float) $draft->price);
        $this->assertFalse($draft->whatsapp_enabled);

        $quote = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/products/quote-search?search=Empanada')
            ->assertOk()
            ->json('data.0');

        $this->assertEquals(750, $quote['price']);
        $this->assertArrayNotHasKey('cost', $quote);
        $this->assertArrayNotHasKey('markup_percent', $quote);
    }

    public function test_pricing_setting_is_the_default_markup(): void
    {
        [, , $token] = $this->adminWithShop();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/shop/pricing', ['markup_percent' => 40])
            ->assertOk()
            ->assertJsonPath('data.markup_percent', 40);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/pricing')
            ->assertOk()
            ->assertJsonPath('data.markup_percent', 40);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/shop/lookups')
            ->assertOk()
            ->assertJsonPath('data.default_markup_percent', 40);
    }

    public function test_shop_can_create_a_supplier(): void
    {
        [, $team, $token] = $this->adminWithShop();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/suppliers', ['name' => 'Panadería Sur'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Panadería Sur');

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'name' => 'Panadería Sur',
            'type_id' => 2,
        ]);
    }

    public function test_manual_cost_recalculates_selling_price(): void
    {
        [, , $token] = $this->adminWithShop();
        $currencyId = Currency::query()->where('code', 'ARS')->value('id');

        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/categories', ['name' => 'Ropa'])
            ->assertCreated()
            ->json('data');

        $created = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/products', [
                'name' => 'Remera',
                'code' => 'REM-200',
                'price' => 1,
                'cost' => 80,
                'markup_percent' => 25,
                'currency_id' => $currencyId,
                'category_id' => $category['id'],
                'catalog_status' => 'publish',
                'stock_status' => 'instock',
                'manage_stock' => false,
                'whatsapp_enabled' => true,
            ])
            ->assertCreated();

        $created->assertJsonPath('data.cost', 80)
            ->assertJsonPath('data.markup_percent', 25)
            ->assertJsonPath('data.price', 100);
    }
}
