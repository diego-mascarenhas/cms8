<?php

namespace Tests\Feature\Api;

use App\Enums\ProductCatalogStatus;
use App\Models\Product;
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
            ->assertJsonPath('data.url', 'https://shop.idoneo.dev/p/www.repuestosav.com/40975');
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
