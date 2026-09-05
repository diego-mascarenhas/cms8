<?php

namespace Tests\Feature\Api;

use App\Enums\ProductCatalogStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicShopCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_is_public_by_catalog_slug_and_code(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'ACEITE HELIX HX8 SHELL 5W40 4L SINTETICO',
            'code' => '40975',
            'short_description' => 'HELIX HX8',
            'price' => 12500,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/40975')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ACEITE HELIX HX8 SHELL 5W40 4L SINTETICO')
            ->assertJsonPath('data.code', '40975')
            ->assertJsonPath('data.short_description', 'HELIX HX8')
            ->assertJsonPath('data.shop_name', 'Repuestos Avenida')
            ->assertJsonPath('data.url', 'https://shop.idoneo.dev/p/www.repuestosav.com/40975')
            ->assertJsonPath('data.shop_url', 'https://shop.idoneo.dev/www.repuestosav.com');
    }

    public function test_draft_product_is_hidden(): void
    {
        $team = $this->makeCatalogTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Borrador',
            'code' => 'DRAFT-1',
            'catalog_status' => ProductCatalogStatus::Draft,
            'status' => false,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/DRAFT-1')
            ->assertNotFound();
    }

    public function test_published_product_is_public_without_livewire_catalog_flag(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = Team::factory()->create();
        $team->setSetting('business_config', [
            'business_name' => 'Repuestos Avenida',
            'business_website' => 'https://www.repuestosav.com',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team = $team->fresh();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Filtro de aceite',
            'code' => '43570',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/43570')
            ->assertOk()
            ->assertJsonPath('data.code', '43570')
            ->assertJsonPath('data.name', 'Filtro de aceite');
    }

    public function test_unknown_shop_slug_returns_not_found(): void
    {
        $this->getJson('/api/public-shop/no-existe/products/40975')->assertNotFound();
    }

    public function test_catalog_lists_published_products_for_shop_slug(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'ACEITE HELIX HX8 SHELL 5W40 4L SINTETICO',
            'code' => '40975',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Borrador',
            'code' => 'DRAFT-1',
            'catalog_status' => ProductCatalogStatus::Draft,
            'status' => false,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shop_name', 'Repuestos Avenida')
            ->assertJsonPath('data.slug', 'www.repuestosav.com')
            ->assertJsonPath('data.products.0.code', '40975')
            ->assertJsonPath('data.products.0.url', 'https://shop.idoneo.dev/p/www.repuestosav.com/40975')
            ->assertJsonPath('data.url', 'https://shop.idoneo.dev/www.repuestosav.com')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonStructure([
                'data' => [
                    'address',
                    'phone',
                    'whatsapp',
                    'hours_label',
                    'categories',
                    'stores',
                    'social' => ['facebook', 'instagram', 'youtube'],
                ],
            ]);
    }

    public function test_catalog_lists_all_active_stores_in_storefront_meta(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Principal',
            'code' => 'MAIN',
            'address' => 'Avda Francia 1198, Rosario',
            'status' => true,
            'is_main' => true,
            'data' => [
                'phone' => '(341) 430-1386',
                'whatsapp' => '543416163917',
                'notes' => 'Santa Fe',
                'hours' => [
                    [
                        'day' => 'mon',
                        'open' => '12:00',
                        'close' => '15:00',
                        'afternoon_open' => '19:30',
                        'afternoon_close' => '23:30',
                        'closed' => false,
                    ],
                ],
            ],
        ]);
        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Palermo',
            'code' => 'PALERMO',
            'address' => 'Honduras 4800, CABA',
            'status' => true,
            'is_main' => false,
            'data' => [
                'phone' => '1144556677',
                'whatsapp' => '5491144556677',
            ],
        ]);
        Store::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Cerrada',
            'code' => 'CLOSED',
            'address' => 'No mostrar',
            'status' => false,
            'is_main' => false,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.stores.0.name', 'Principal')
            ->assertJsonPath('data.stores.0.is_main', true)
            ->assertJsonPath('data.stores.0.address', 'Avda Francia 1198, Rosario')
            ->assertJsonPath('data.stores.0.hours_label', 'De 12:00 a 15:00 y de 19:30 a 23:30hs')
            ->assertJsonPath('data.stores.1.name', 'Palermo')
            ->assertJsonPath('data.stores.1.address', 'Honduras 4800, CABA')
            ->assertJsonPath('data.address', 'Avda Francia 1198, Rosario')
            ->assertJsonCount(2, 'data.stores');
    }

    public function test_unknown_catalog_slug_returns_not_found(): void
    {
        $this->getJson('/api/public-shop/no-existe')->assertNotFound();
    }

    public function test_catalog_includes_product_configurator_when_present(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Docena de empanadas',
            'code' => 'DOCENA-1',
            'short_description' => 'Esto es un ejemplo',
            'price' => 580,
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
            'configurator' => [
                'groups' => [
                    [
                        'id' => 'coccion',
                        'name' => 'Cocción empanadas',
                        'type' => 'single',
                        'max' => 1,
                        'required' => true,
                        'choices' => [
                            ['id' => 'horno', 'name' => 'Al horno', 'price' => 0],
                            ['id' => 'fritas', 'name' => 'Fritas', 'price' => 0],
                        ],
                    ],
                    [
                        'id' => 'gustos',
                        'name' => 'Gustos empanadas x docena',
                        'type' => 'quantity',
                        'max' => 2,
                        'min' => 2,
                        'required' => true,
                        'choices' => [
                            ['id' => 'carne', 'name' => 'Carne dulce', 'price' => 0, 'units' => 6],
                            ['id' => 'jyq', 'name' => 'Jamón y queso', 'price' => 0, 'units' => 6],
                        ],
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/DOCENA-1')
            ->assertOk()
            ->assertJsonPath('data.slug', 'docena-de-empanadas')
            ->assertJsonPath('data.price_amount', 580)
            ->assertJsonPath('data.configurator.groups.0.id', 'coccion')
            ->assertJsonPath('data.configurator.groups.0.type', 'single')
            ->assertJsonPath('data.configurator.groups.1.type', 'quantity')
            ->assertJsonPath('data.configurator.groups.1.choices.0.units', 6);
    }

    private function makeCatalogTeam(): Team
    {
        $team = Team::factory()->create();
        $team->setSetting('business_config', [
            'business_name' => 'Repuestos Avenida',
            'business_website' => 'https://www.repuestosav.com',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->setSetting('public_catalog_enabled', true, [
            'group' => 'public_shop',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        return $team->fresh();
    }
}
