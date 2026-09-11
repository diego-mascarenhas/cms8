<?php

namespace Tests\Feature\Api;

use App\Enums\ProductCatalogStatus;
use App\Models\Product;
use App\Models\Team;
use App\Support\ShopCatalogApiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicShopCatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.shop_catalog_ttl' => -1]);
        Cache::flush();
    }

    public function test_catalog_index_is_served_from_cache_until_product_changes(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Producto cache',
            'code' => 'CACHE-1',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Producto cache');

        $generation = ShopCatalogApiCache::currentGeneration((int) $team->id);
        $cacheKey = ShopCatalogApiCache::indexCacheKey((int) $team->id, $generation, 'www.repuestosav.com');
        $this->assertTrue(Cache::has($cacheKey));

        Product::withoutEvents(function () use ($product): void
        {
            $product->update(['name' => 'Nombre sin invalidar']);
        });

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Producto cache');

        $product->update(['name' => 'Producto actualizado']);

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Producto actualizado');
    }

    public function test_catalog_product_show_is_invalidated_on_save(): void
    {
        config(['services.shop.url' => 'https://shop.idoneo.dev']);

        $team = $this->makeCatalogTeam();
        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Detalle cache',
            'code' => 'SHOW-1',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/SHOW-1')
            ->assertOk()
            ->assertJsonPath('data.name', 'Detalle cache');

        $product->update(['name' => 'Detalle nuevo']);

        $this->getJson('/api/public-shop/www.repuestosav.com/products/SHOW-1')
            ->assertOk()
            ->assertJsonPath('data.name', 'Detalle nuevo');
    }

    public function test_catalog_cache_can_be_disabled(): void
    {
        config([
            'services.shop.url' => 'https://shop.idoneo.dev',
            'cache.shop_catalog_ttl' => 0,
        ]);

        $team = $this->makeCatalogTeam();
        $product = Product::factory()->create([
            'team_id' => $team->id,
            'name' => 'Sin cache',
            'code' => 'NOCACHE-1',
            'catalog_status' => ProductCatalogStatus::Publish,
            'status' => true,
        ]);

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Sin cache');

        Product::withoutEvents(function () use ($product): void
        {
            $product->update(['name' => 'Cambio inmediato']);
        });

        $this->getJson('/api/public-shop/www.repuestosav.com')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Cambio inmediato');
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
