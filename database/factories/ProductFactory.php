<?php

namespace Database\Factories;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productNames = [
            'Hosting Web Básico',
            'Dominio .com',
            'SSL Certificate',
            'Backup Automático',
            'Desarrollo Web',
            'App Móvil',
            'Consultoría IT',
            'Soporte Técnico',
            'Migración de Servidor',
            'Optimización SEO',
        ];

        $descriptions = [
            'Servicio de hosting web con 10GB de espacio y 100GB de transferencia mensual',
            'Registro de dominio .com por 1 año con protección de privacidad',
            'Certificado SSL para asegurar tu sitio web',
            'Backup automático diario de tu base de datos y archivos',
            'Desarrollo de sitio web personalizado con diseño responsive',
            'Desarrollo de aplicación móvil nativa para iOS y Android',
            'Consultoría en tecnología de la información',
            'Soporte técnico 24/7 para todos nuestros servicios',
            'Migración completa de tu servidor actual',
            'Optimización de tu sitio web para motores de búsqueda',
        ];

        $prices = [29.99, 19.99, 49.99, 15.99, 999.99, 1499.99, 199.99, 79.99, 299.99, 399.99];

        return [
            'name' => $this->faker->randomElement($productNames),
            'code' => strtoupper($this->faker->unique()->bothify('PRD-###??')),
            'description' => $this->faker->randomElement($descriptions),
            'short_description' => null,
            'price' => $this->faker->randomElement($prices),
            'sale_price' => null,
            'currency_id' => function ()
            {
                return Currency::query()->where('code', 'ARS')->value('id')
                    ?? Currency::query()->firstOrCreate(
                        ['code' => 'ARS'],
                        ['name' => 'Peso argentino', 'symbol' => '$', 'status' => true],
                    )->id;
            },
            'store_id' => null,
            'available_in_all_stores' => true,
            'brand_id' => null,
            'category_id' => function (array $attributes)
            {
                return Category::factory()->create([
                    'team_id' => $attributes['team_id'] ?? Team::factory(),
                ])->id;
            },
            'status' => true,
            'catalog_status' => ProductCatalogStatus::Publish,
            'stock_status' => ProductStockStatus::InStock,
            'manage_stock' => false,
            'stock_quantity' => null,
            'assortment_size' => null,
            'whatsapp_enabled' => true,
            'team_id' => Team::inRandomOrder()->first()?->id ?? 1,
            'image' => null,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
            'catalog_status' => ProductCatalogStatus::Draft,
        ]);
    }

    /**
     * Indicate that the product is not available via WhatsApp.
     */
    public function whatsappDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'whatsapp_enabled' => false,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void
        {
            if ($product->variants()->withoutGlobalScope('team')->exists())
            {
                return;
            }

            app(\App\Services\ProductVariantCatalogService::class)->ensureDefaultVariant($product->fresh());
        });
    }
}
