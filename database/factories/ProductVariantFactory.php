<?php

namespace Database\Factories;

use App\Enums\ProductStockStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'team_id' => 1,
            'product_id' => Product::factory(),
            'sku' => null,
            'price' => 10,
            'sale_price' => null,
            'stock_status' => ProductStockStatus::InStock,
            'manage_stock' => false,
            'stock_quantity' => null,
            'is_default' => true,
            'position' => 0,
        ];
    }
}
