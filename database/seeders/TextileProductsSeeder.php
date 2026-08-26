<?php

namespace Database\Seeders;

use App\Enums\ProductCatalogStatus;
use App\Enums\ProductStockStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\Store;
use App\Models\Team;
use App\Models\User;
use App\Services\ProductVariantCatalogService;
use App\Services\TeamModulesByPricingPlanSyncer;
use Illuminate\Database\Seeder;

/**
 * Textile demo: only categories Ropa, Calzado, Accesorios and their products (local DB, no WooCommerce).
 * Removes other product-module categories and all team products for this team, then reseeds the catalogue.
 *
 * Run: php artisan db:seed --class=TextileProductsSeeder
 */
class TextileProductsSeeder extends Seeder
{
    private const TEXTILE_CATEGORY_NAMES = ['Ropa', 'Calzado', 'Accesorios'];

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first()
            ?? Team::query()->where('name', "Demo's Team")->first();

        if (! $team)
        {
            $admin = User::query()->where('email', 'admin@humano.app')->first();
            if ($admin?->current_team_id)
            {
                $team = Team::query()->find($admin->current_team_id);
            }
        }

        $team ??= Team::query()->find(1);

        if (! $team)
        {
            $this->command?->warn('TextileProductsSeeder: No demo team found (expected "Demo" first, then "Demo\'s Team", admin current team, or id 1). Skipping.');

            return;
        }

        $productsModule = Module::query()->where('key', 'products')->first();

        if (! $productsModule)
        {
            $this->command?->error('TextileProductsSeeder: products module missing. Run ModuleSeeder first.');

            return;
        }

        $this->command?->info('🧵 Seeding textile categories and products for: '.$team->name.' (id '.$team->id.')');

        Product::withoutGlobalScope('team')->where('team_id', $team->id)->delete();

        Category::withTrashed()
            ->where('team_id', $team->id)
            ->where('module_id', $productsModule->id)
            ->whereNotIn('name', self::TEXTILE_CATEGORY_NAMES)
            ->forceDelete();

        $currencyId = Currency::query()->where('code', 'ARS')->value('id')
            ?? Currency::query()->value('id');

        if (! $currencyId)
        {
            $this->command?->error('TextileProductsSeeder: no currency. Run CurrencySeeder first.');

            return;
        }

        $categoryDefinitions = [
            ['name' => 'Ropa', 'description' => 'Prendas de vestir: parte superior e inferior'],
            ['name' => 'Calzado', 'description' => 'Zapatillas, botas, sandalias y calzado urbano'],
            ['name' => 'Accesorios', 'description' => 'Complementos: bolsos, cinturones, sombreros'],
        ];
        $this->restoreTextileCategoriesIfTrashed($team->id, $productsModule->id);

        $categoryIdsByName = [];
        foreach ($categoryDefinitions as $order => $definition)
        {
            $category = Category::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'module_id' => $productsModule->id,
                    'team_id' => $team->id,
                ],
                [
                    'description' => $definition['description'],
                    'parent_id' => null,
                    'status' => true,
                    'order' => $order,
                ],
            );
            $categoryIdsByName[$definition['name']] = $category->id;
        }

        $ropa = $categoryIdsByName['Ropa'];
        $calzado = $categoryIdsByName['Calzado'];
        $accesorios = $categoryIdsByName['Accesorios'];

        $u = 'https://images.unsplash.com';
        $q = '?auto=format&fit=crop&w=640&q=80';

        $stores = [
            Store::withoutGlobalScope('team')->updateOrCreate(
                ['team_id' => $team->id, 'code' => 'MAIN'],
                [
                    'name' => 'Principal',
                    'address' => null,
                    'status' => true,
                    'is_main' => true,
                ],
            ),
            Store::withoutGlobalScope('team')->updateOrCreate(
                ['team_id' => $team->id, 'name' => 'Tienda Centro'],
                ['code' => 'CENTRO', 'address' => 'Av. Centro 123', 'status' => true, 'is_main' => false],
            ),
            Store::withoutGlobalScope('team')->updateOrCreate(
                ['team_id' => $team->id, 'name' => 'Tienda Palermo'],
                ['code' => 'PALERMO', 'address' => 'Calle Palermo 456', 'status' => true, 'is_main' => false],
            ),
        ];

        Store::withoutGlobalScope('team')
            ->where('team_id', $team->id)
            ->where('code', '!=', 'MAIN')
            ->update(['is_main' => false]);

        $catalogue = [
            ['name' => 'Camiseta básica algodón', 'code' => 'CAM-BAS-ALG', 'sizes' => ['S', 'M', 'L', 'XL'], 'colors' => ['Blanco', 'Negro', 'Azul'], 'short' => 'Camiseta básica unisex, algodón peinado. Ideal para el día a día.', 'description' => 'Corte regular, manga corta, varios colores.', 'price' => 24990.00, 'cat' => $ropa, 'image' => $u.'/photo-1521572163474-6864f9cf17ab'.$q],
            ['name' => 'Camisa Oxford', 'code' => 'CAM-OXF-001', 'sizes' => ['S', 'M', 'L'], 'colors' => ['Celeste', 'Blanco'], 'short' => 'Camisa formal de tejido oxford, fácil de planchar.', 'description' => 'Tejido oxford, cuello clásico, ideal oficina.', 'price' => 54900.00, 'cat' => $ropa, 'image' => $u.'/photo-1602810318383-e386cc2a3ccf'.$q],
            ['name' => 'Jersey de punto', 'code' => 'JER-PUN-001', 'sizes' => ['M', 'L', 'XL'], 'colors' => ['Gris', 'Negro'], 'short' => 'Jersey de punto suave, cálido sin pesar.', 'description' => 'Mezcla lana suave, cuello redondo.', 'price' => 72900.00, 'cat' => $ropa, 'image' => $u.'/photo-1576566584346-55d9a9f24b7b'.$q],
            ['name' => 'Pantalón chino', 'code' => 'PAN-CHI-001', 'sizes' => ['38', '40', '42', '44'], 'colors' => ['Beige', 'Azul'], 'short' => 'Pantalón chino con ligero stretch para mayor comodidad.', 'description' => 'Talle medio, tela stretch ligera.', 'price' => 62900.00, 'cat' => $ropa, 'image' => $u.'/photo-1506629082955-511b1e56f768'.$q],
            ['name' => 'Vaquero slim', 'code' => 'VAQ-SLI-001', 'sizes' => ['38', '40', '42', '44'], 'colors' => ['Denim claro', 'Denim oscuro'], 'short' => 'Vaquero corte slim, denim versátil temporada tras temporada.', 'description' => 'Denim medio, cinco bolsillos.', 'price' => 86900.00, 'cat' => $ropa, 'image' => $u.'/photo-1542272604-787c3835535d'.$q],
            ['name' => 'Vestido midi', 'code' => 'VES-MID-001', 'sizes' => ['S', 'M', 'L'], 'colors' => ['Negro', 'Rojo'], 'short' => 'Vestido midi con caída fluida, ocasión especial u oficina.', 'description' => 'Silueta fluida, forro interior.', 'price' => 99900.00, 'cat' => $ropa, 'image' => $u.'/photo-1595777457583-95e059d581b8'.$q],
            ['name' => 'Chaqueta ligera', 'code' => 'CHA-LIG-001', 'sizes' => ['M', 'L', 'XL'], 'colors' => ['Verde', 'Negro'], 'short' => 'Chaqueta cortavientos ligera con capucha plegable.', 'description' => 'Impermeable compacto, capucha oculta.', 'price' => 109900.00, 'cat' => $ropa, 'image' => $u.'/photo-1591047139829-d91aecb6caea'.$q],
            ['name' => 'Zapatillas urbanas', 'code' => 'ZAP-URB-001', 'sizes' => ['39', '40', '41', '42', '43'], 'colors' => ['Blanco', 'Negro'], 'short' => 'Zapatillas de día a día con suela amortiguada.', 'description' => 'Suela EVA, upper mesh transpirable.', 'price' => 92900.00, 'cat' => $calzado, 'image' => $u.'/photo-1542291026-7eec264c27ff'.$q],
            ['name' => 'Botines de cuero', 'code' => 'BOT-CUE-001', 'sizes' => ['39', '40', '41', '42'], 'colors' => ['Marrón', 'Negro'], 'short' => 'Botines de cuero con cierre práctico y suela estable.', 'description' => 'Cierre lateral, tacón bajo estable.', 'price' => 149900.00, 'cat' => $calzado, 'image' => $u.'/photo-1608256241007-07ae575fe734'.$q],
            ['name' => 'Sandalias planas', 'code' => 'SAN-PLA-001', 'sizes' => ['36', '37', '38', '39', '40'], 'colors' => ['Natural', 'Negro'], 'short' => 'Sandalias planas con plantilla confort para verano.', 'description' => 'Plantilla acolchada, tiras ajustables.', 'price' => 43900.00, 'cat' => $calzado, 'image' => $u.'/photo-1603487746746-09b3a9b2d86c'.$q],
            ['name' => 'Zapatos derby', 'code' => 'ZAP-DER-001', 'sizes' => ['40', '41', '42', '43'], 'colors' => ['Negro', 'Marrón'], 'short' => 'Zapatos derby de vestir, acabado elegante para eventos.', 'description' => 'Acabado mate, suela de goma.', 'price' => 119900.00, 'cat' => $calzado, 'image' => $u.'/photo-1533867617858-e7b97e060509'.$q],
            ['name' => 'Bolso tote mediano', 'code' => 'BOL-TOT-001', 'sizes' => [], 'colors' => ['Negro', 'Suela'], 'short' => 'Bolso tote mediano; caben tablet y essentials diarios.', 'description' => 'Cuero sintético, asa larga y corta.', 'price' => 69900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1594223274512-ad4803739b7c'.$q],
            ['name' => 'Cinturón reversible', 'code' => 'CIN-REV-001', 'sizes' => ['M', 'L'], 'colors' => ['Marrón/Negro'], 'short' => 'Cinturón reversible, dos acabados en una sola correa.', 'description' => 'Dos tonos en una sola pieza.', 'price' => 37900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1624378519965-31f2e806f500'.$q],
            ['name' => 'Bufanda de lana', 'code' => 'BUF-LAN-001', 'sizes' => [], 'colors' => ['Gris', 'Bordó'], 'short' => 'Bufanda de lana suave, largo ideal para abrigar.', 'description' => 'Tejido suave, 180 x 30 cm.', 'price' => 29900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1520903920243-13b24e63aad5'.$q],
            ['name' => 'Gorra ajustable', 'code' => 'GOR-AJU-001', 'sizes' => [], 'colors' => ['Negro', 'Azul'], 'short' => 'Gorra snapback ajustable, visera curva clásica.', 'description' => 'Visera curva, cierre snapback.', 'price' => 27900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1588850561407-ed78a312d3db'.$q],
            ['name' => 'Gafas de sol polarizadas', 'code' => 'GAF-SOL-001', 'sizes' => [], 'colors' => ['Negro'], 'short' => 'Gafas de sol polarizadas con protección UV400.', 'description' => 'Protección UV400, montura ligera.', 'price' => 59900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1572635196237-14b3f281503f'.$q],
            ['name' => 'Mochila city 20L', 'code' => 'MOC-CIT-001', 'sizes' => [], 'colors' => ['Negro', 'Gris'], 'short' => 'Mochila urbana 20 L con hueco acolchado para portátil.', 'description' => 'Compartimento laptop 15", espalda acolchada.', 'price' => 81900.00, 'cat' => $accesorios, 'image' => $u.'/photo-1553062407-98eeb64c6a62'.$q],
            ['name' => 'Calcetines pack x3', 'code' => 'CAL-P3-001', 'sizes' => ['35-38', '39-42'], 'colors' => ['Mixto'], 'short' => 'Pack de tres pares, algodón transpirable altura media.', 'description' => 'Algodón peinado, altura media.', 'price' => 15900.00, 'cat' => $ropa, 'image' => $u.'/photo-1586350977777-caa5a7988cff'.$q],
        ];

        foreach ($catalogue as $index => $row)
        {
            $product = Product::withoutGlobalScope('team')->updateOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => $row['name'],
                ],
                [
                    'description' => '<p>'.e($row['description']).'</p>',
                    'code' => $row['code'],
                    'short_description' => '<p>'.e($row['short']).'</p>',
                    'price' => $row['price'],
                    'sale_price' => null,
                    'currency_id' => $currencyId,
                    'category_id' => $row['cat'],
                    'status' => true,
                    'catalog_status' => ProductCatalogStatus::Publish,
                    'stock_status' => ProductStockStatus::InStock,
                    'manage_stock' => false,
                    'stock_quantity' => null,
                    'whatsapp_enabled' => $index % 3 !== 0,
                    'image' => $row['image'],
                    'store_id' => $stores[$index % count($stores)]->id,
                ],
            );

            app(ProductVariantCatalogService::class)->sync(
                $product,
                [
                    ['name' => 'Talle', 'values' => $row['sizes']],
                    ['name' => 'Color', 'values' => $row['colors']],
                ],
                [],
            );
        }

        $this->command?->info('✅ Textile seed done: '.count($catalogue).' products, 3 categories (Ropa, Calzado, Accesorios).');

        $this->syncDemoTeamModulesFromPricingPlan($team);
    }

    /**
     * Demo catalogue data is seeded, then DEMO_DEV_MODULES re-enables shop modules
     * (products, stores, orders) after the Humano plan sync.
     */
    private function syncDemoTeamModulesFromPricingPlan(Team $team): void
    {
        if (! in_array($team->name, ['Demo', "Demo's Team"], true))
        {
            return;
        }

        $planSlug = (string) config('humano_pricing.demo_team_plan_slug', 'assistant');
        if (! in_array($planSlug, array_keys(config('humano_pricing.plan_team_modules', [])), true))
        {
            $planSlug = 'assistant';
        }

        app(TeamModulesByPricingPlanSyncer::class)->syncForHumanoPricingPlan($team, $planSlug);

        foreach (TeamDemoSeeder::DEMO_DEV_MODULES as $moduleKey)
        {
            $team->enableModule($moduleKey);
        }

        $this->command?->info("🔧 Demo team modules re-synced to Humano plan «{$planSlug}» (shop modules stay on via DEMO_DEV_MODULES).");
    }

    private function restoreTextileCategoriesIfTrashed(int $teamId, int $moduleId): void
    {
        foreach (self::TEXTILE_CATEGORY_NAMES as $name)
        {
            $category = Category::withTrashed()
                ->where('team_id', $teamId)
                ->where('module_id', $moduleId)
                ->where('name', $name)
                ->first();
            if ($category && $category->trashed())
            {
                $category->restore();
            }
        }
    }
}
