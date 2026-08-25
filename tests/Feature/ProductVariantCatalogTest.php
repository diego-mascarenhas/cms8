<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use App\Services\ProductVariantCatalogService;
use App\Services\ShoppingCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_autoparts_product_has_one_default_variant(): void
    {
        $product = $this->makeProduct('Pastilla Bosch');

        $this->assertSame(1, $product->variants()->count());
        $this->assertTrue((bool) $product->defaultVariant()?->is_default);
        $this->assertSame('', (string) $product->defaultVariant()?->optionLabel());
    }

    public function test_clothing_builds_size_and_color_combinations(): void
    {
        $product = $this->makeProduct('Remera Nike');
        app(ProductVariantCatalogService::class)->sync($product, [
            ['name' => 'Talle', 'values' => ['S', 'M', 'L']],
            ['name' => 'Color', 'values' => ['Negro', 'Blanco']],
        ]);

        $product->refresh();
        $this->assertEqualsCanonicalizing(['Talle', 'Color'], $product->options()->pluck('name')->all());
        $this->assertSame(6, $product->variants()->count());
        $this->assertEqualsCanonicalizing(
            ['S', 'M', 'L'],
            $product->optionValuesNamed('Talle'),
        );
    }

    public function test_empanada_dozen_adds_a_cart_line_per_flavor(): void
    {
        $product = $this->makeProduct('Docena de empanadas', ['assortment_size' => 12]);
        app(ProductVariantCatalogService::class)->sync($product, [
            ['name' => 'Gusto', 'values' => ['Carne', 'Pollo', 'JyQ', 'Cebolla']],
        ]);
        $product->load(['variants.optionValues.option']);

        $this->assertSame(12, $product->assortment_size);
        $this->assertSame(4, $product->variants()->count());

        $carne = $product->variants->first(
            fn ($variant): bool => $variant->optionLabel() === 'Carne',
        );
        $pollo = $product->variants->first(
            fn ($variant): bool => $variant->optionLabel() === 'Pollo',
        );
        $this->assertNotNull($carne);
        $this->assertNotNull($pollo);

        $carts = app(ShoppingCartService::class);
        $cart = $carts->forWhatsApp((int) $product->team_id, '5491199900300');
        $carts->addVariant($cart, $carne, 4);
        $carts->addVariant($cart, $pollo, 8);

        $lines = $carts->lines($cart);
        $this->assertSame(2, $lines->count());
        $this->assertSame(12, $carts->quantity($cart));
        $this->assertEqualsCanonicalizing(
            [$carne->id, $pollo->id],
            $lines->pluck('variant_id')->all(),
        );
        $this->assertContains('Carne', $lines->map(fn (object $line): string => (string) $line->attributes->option_label)->all());
    }

    public function test_clearing_options_collapses_to_one_variant(): void
    {
        $product = $this->makeProduct('Remera');
        $catalog = app(ProductVariantCatalogService::class);
        $catalog->sync($product, [
            ['name' => 'Talle', 'values' => ['S', 'M']],
        ]);
        $this->assertSame(2, $product->variants()->count());

        $catalog->sync($product->fresh(), []);
        $product->refresh();

        $this->assertSame(0, $product->options()->count());
        $this->assertSame(1, $product->variants()->count());
        $this->assertTrue((bool) $product->defaultVariant()?->is_default);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeProduct(string $name, array $extra = []): Product
    {
        $team = Team::factory()->create();
        $currencyId = Currency::query()->firstOrCreate(
            ['code' => 'ARS'],
            ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
        )->id;
        $category = Category::withoutGlobalScopes()->create([
            'name' => 'Catálogo',
            'module_id' => null,
            'team_id' => $team->id,
            'parent_id' => null,
            'status' => true,
            'order' => 0,
        ]);

        return Product::factory()->create(array_merge([
            'team_id' => $team->id,
            'name' => $name,
            'price' => 100,
            'currency_id' => $currencyId,
            'category_id' => $category->id,
        ], $extra));
    }
}
