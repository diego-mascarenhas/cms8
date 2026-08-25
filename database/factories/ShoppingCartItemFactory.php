<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingCartItem>
 */
class ShoppingCartItemFactory extends Factory
{
    protected $model = ShoppingCartItem::class;

    public function definition(): array
    {
        return [
            'shopping_cart_id' => ShoppingCart::factory(),
            'team_id' => fn (array $attributes) => ShoppingCart::withoutGlobalScope('team')->find($attributes['shopping_cart_id'])?->team_id,
            'product_id' => Product::factory(),
            'product_variant_id' => function (array $attributes)
            {
                $product = Product::withoutGlobalScope('team')->find($attributes['product_id']);

                return $product?->defaultVariant()?->id;
            },
            'name' => $this->faker->words(3, true),
            'option_label' => null,
            'price' => $this->faker->randomFloat(2, 10, 2000),
            'quantity' => $this->faker->numberBetween(1, 4),
            'currency_id' => null,
            'store_id' => null,
            'category_name' => 'General',
            'description' => null,
        ];
    }
}
