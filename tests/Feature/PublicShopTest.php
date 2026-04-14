<?php

namespace Tests\Feature;

use App\Livewire\PublicShop\ShoppingAssistant;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DatabaseStorageModel;
use App\Models\Module;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicShopTest extends TestCase
{
    use RefreshDatabase;

    private function makeShopTeam(): Team
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $module = Module::query()->create([
            'name' => 'Products',
            'key' => 'products',
            'icon' => 'ti-package',
            'description' => null,
            'is_core' => true,
            'group' => 'commerce',
            'order' => 1,
            'status' => 1,
        ]);

        Currency::query()->create([
            'id' => 1,
            'code' => 'ARS',
            'name' => 'Peso',
            'symbol' => '$',
            'status' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Cat',
            'module_id' => $module->id,
            'team_id' => $team->id,
            'status' => 1,
            'order' => 0,
        ]);

        Product::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'name' => 'Alpha Product',
            'code' => 'ALP-1',
            'description' => 'Desc',
            'price' => 10,
            'sale_price' => null,
            'currency_id' => 1,
            'category_id' => $category->id,
            'store_id' => null,
            'status' => true,
            'catalog_status' => 'publish',
            'stock_status' => 'instock',
            'whatsapp_enabled' => true,
        ]);

        $team->setSetting('business_config', [
            'business_name' => 'Acme Demo Store',
            'business_website' => 'https://WWW.shop-demo.example/path/',
        ], [
            'type' => 'json',
            'group' => 'business-config',
        ]);
        $team->refresh();
        $team->setSetting('public_catalog_enabled', true, [
            'group' => 'public_shop',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);
        $team->setSetting('whatsapp_from', '5491112345678', [
            'group' => 'twilio',
            'type' => 'string',
            'is_encrypted' => false,
        ]);

        return $team->fresh();
    }

    private function publicShopDomainForTeam(Team $team): string
    {
        return Team::normalizePublicShopDomain(
            (string) ($team->getDecodedBusinessConfig()['business_website'] ?? ''),
        ) ?? '';
    }

    public function test_public_shop_page_loads_when_enabled(): void
    {
        $team = $this->makeShopTeam();
        $slug = $this->publicShopDomainForTeam($team);
        $this->assertSame('www.shop-demo.example', $slug);

        $response = $this->get(url('/shop/'.$slug));
        $response->assertOk();
        $response->assertSeeLivewire(ShoppingAssistant::class);
    }

    public function test_public_shop_returns_404_when_disabled(): void
    {
        $team = $this->makeShopTeam();
        $slug = $this->publicShopDomainForTeam($team);
        $team->setSetting('public_catalog_enabled', false, [
            'group' => 'public_shop',
            'type' => 'boolean',
            'is_encrypted' => false,
        ]);

        $this->get(url('/shop/'.$slug))->assertNotFound();
    }

    public function test_public_shop_unknown_slug_404(): void
    {
        $this->makeShopTeam();

        $this->get(url('/shop/no-existe'))->assertNotFound();
    }

    public function test_public_shop_loads_by_business_name_slug(): void
    {
        $team = $this->makeShopTeam();

        $this->get(url('/shop/acme-demo-store'))
            ->assertOk()
            ->assertSeeLivewire(ShoppingAssistant::class);

        $this->assertStringEndsWith('/shop/www.shop-demo.example', $team->publicCatalogShopUrl() ?? '');
    }

    public function test_public_shop_name_slug_ambiguous_returns_404(): void
    {
        $user = User::factory()->create();
        foreach (['first', 'second'] as $suffix)
        {
            $team = Team::factory()->create([
                'user_id' => $user->id,
                'name' => 'Team '.$suffix,
            ]);
            $team->setSetting('business_config', [
                'business_name' => 'Duplicate Brand SL',
            ], [
                'type' => 'json',
                'group' => 'business-config',
            ]);
            $team->setSetting('public_catalog_enabled', true, [
                'group' => 'public_shop',
                'type' => 'boolean',
                'is_encrypted' => false,
            ]);
        }

        $this->get(url('/shop/duplicate-brand-sl'))->assertNotFound();
    }

    public function test_livewire_can_add_to_cart_and_redirect_whatsapp(): void
    {
        $team = $this->makeShopTeam();
        $slug = $this->publicShopDomainForTeam($team);
        $product = Product::withoutGlobalScope('team')->where('team_id', $team->id)->firstOrFail();

        Livewire::test(ShoppingAssistant::class, ['slug' => $slug])
            ->call('addToCart', (int) $product->id)
            ->assertSet('cart.'.((string) $product->id), 1)
            ->call('checkoutWhatsApp')
            ->assertRedirect();
    }

    public function test_cart_persists_to_cart_storage_table(): void
    {
        $team = $this->makeShopTeam();
        $slug = $this->publicShopDomainForTeam($team);
        $product = Product::withoutGlobalScope('team')->where('team_id', $team->id)->firstOrFail();

        Livewire::test(ShoppingAssistant::class, ['slug' => $slug])
            ->call('addToCart', (int) $product->id)
            ->assertSet('cart.'.((string) $product->id), 1);

        $ids = DatabaseStorageModel::query()->pluck('id')->all();
        $this->assertNotEmpty($ids, 'Expected cart_storage to persist the public shop cart.');

        $prefix = 'pubshop_'.$team->id.'_';
        $this->assertTrue(
            collect($ids)->contains(fn (string $id) => str_starts_with($id, $prefix)),
        );
    }
}
