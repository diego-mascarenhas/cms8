<?php

namespace Tests\Feature\Api;

use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialDocumentInterpreter;
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

    public function test_a_dropped_file_is_previewed_and_confirmed_with_the_edited_cost(): void
    {
        [, $team, $token] = $this->adminWithShop();
        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        $supplier = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'type_id' => 2,
            'status_id' => 2,
            'name' => 'Textil Norte',
        ]);
        $csv = "code,name,cost\nREM-9,Remera,1000\n";

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/supplier-price-lists/preview', [
                'file' => UploadedFile::fake()->createWithContent('lista.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'price_list')
            ->assertJsonPath('data.lines.0.name', 'Remera')
            ->assertJsonPath('data.lines.0.cost', 1000);

        $this->assertDatabaseCount('products', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/shop/supplier-price-lists/confirm', [
                'enterprise_id' => $supplier->id,
                'currency_id' => $currencyId,
                'markup_percent' => 50,
                'lines' => [[
                    'code' => 'REM-9',
                    'name' => 'Remera blanca',
                    'cost' => 800,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $product = Product::query()->where('code', 'REM-9')->first();
        $this->assertNotNull($product);
        $this->assertSame('Remera blanca', $product->name);
        $this->assertEquals(800, (float) $product->cost);
        $this->assertEquals(1200, (float) $product->price);
    }

    public function test_a_read_price_list_creates_the_supplier_and_the_selling_price(): void
    {
        [, $team, $token] = $this->adminWithShop();
        $currencyId = Currency::query()->where('code', 'ARS')->value('id');

        $this->mock(CommercialDocumentInterpreter::class, function ($mock): void
        {
            $mock->shouldReceive('interpret')->once()->andReturn([
                'kind' => 'price_list',
                'supplier_name' => 'Maxikol',
                'currency' => 'ARS',
                'lines' => [[
                    'code' => 'A1',
                    'name' => 'Aceite',
                    'cost' => 1000.0,
                    'quantity' => null,
                    'unit' => null,
                    'barcode' => null,
                    'oem' => null,
                ]],
            ]);
        });

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/supplier-price-lists/import', [
                'file' => UploadedFile::fake()->create('maxikol.pdf', 20, 'application/pdf'),
                'currency_id' => $currencyId,
                'markup_percent' => 50,
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('enterprises', [
            'team_id' => $team->id,
            'name' => 'Maxikol',
            'type_id' => 2,
        ]);

        $product = Product::query()->where('name', 'Aceite')->first();
        $this->assertNotNull($product);
        $this->assertEquals(1000, (float) $product->cost);
        $this->assertEquals(1500, (float) $product->price);
    }

    public function test_a_requisition_uses_the_catalog_selling_price(): void
    {
        [, $team, $token] = $this->adminWithShop();
        $currencyId = Currency::query()->where('code', 'ARS')->value('id');
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Contenedor',
            'code' => '17 08 58',
            'price' => 1,
            'cost' => 100,
            'markup_percent' => 100,
            'currency_id' => $currencyId,
        ]);

        $this->mock(CommercialDocumentInterpreter::class, function ($mock): void
        {
            $mock->shouldReceive('interpret')->once()->andReturn([
                'kind' => 'requisition',
                'supplier_name' => null,
                'currency' => null,
                'lines' => [[
                    'code' => '170858',
                    'name' => 'STAINLESS STEEL CONTAINERS',
                    'cost' => null,
                    'quantity' => 6,
                    'unit' => 'PC',
                    'barcode' => null,
                    'oem' => null,
                ]],
            ]);
        });

        $line = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/shop/products/quote-document', [
                'file' => UploadedFile::fake()->create('galley.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertOk()
            ->json('data.lines.0');

        $this->assertTrue($line['matched']);
        $this->assertSame(6, (int) $line['quantity']);
        $this->assertEquals(200, $line['unit_price']);
        $this->assertArrayNotHasKey('cost', $line);
    }
}
