<?php

namespace Database\Seeders;

use App\Models\SubscriptionProduct;
use Illuminate\Database\Seeder;

class SubscriptionProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stripeSecret = (string) (config('cashier.secret') ?? config('services.stripe.secret') ?? '');
        if (blank(trim($stripeSecret)))
        {
            $this->command?->warn('Skipping SubscriptionProductSeeder: missing STRIPE_SECRET / CASHIER_SECRET.');

            return;
        }

        // Mailer Products (from https://revisionalpha.com/emailer)
        $mailerProducts = [
            [
                'stripe_id' => 'prod_TgFjxc4y8IGwPW',
                'stripe_product' => 'prod_TgFjxc4y8IGwPW',
                'stripe_price' => 'price_1SitE6RwN51ygFdeuoV0tTLf',
                'name' => 'Mailer Basic',
                'description' => 'Perfecto para pequeñas empresas que están comenzando con email marketing',
                'category' => 'mailer',
                'plan' => 'basic',
                'type' => 'mailer',
                'currency' => 'eur',
                'unit_amount' => 15.99,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_TgFmhp9iYHH3Q4',
                'stripe_product' => 'prod_TgFmhp9iYHH3Q4',
                'stripe_price' => 'price_1SitGURwN51ygFdeoS4K9YDn',
                'name' => 'Mailer Foundation',
                'description' => 'Ideal para empresas que necesitan escalar sus campañas de email marketing',
                'category' => 'mailer',
                'plan' => 'foundation',
                'type' => 'mailer',
                'currency' => 'eur',
                'unit_amount' => 35.99,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_TgFmbs3kRkpGxu',
                'stripe_product' => 'prod_TgFmbs3kRkpGxu',
                'stripe_price' => 'price_1SitHHRwN51ygFde3eMKuUtU',
                'name' => 'Mailer Scale',
                'description' => 'Solución completa para empresas que requieren máxima personalización y soporte',
                'category' => 'mailer',
                'plan' => 'scale',
                'type' => 'mailer',
                'currency' => 'eur',
                'unit_amount' => 119.99,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        // Mentoring Products (Strategic Growth Framework)
        $mentoringProducts = [
            [
                'stripe_id' => 'prod_ToCuDuMzFmqiCq',
                'stripe_product' => 'prod_ToCuDuMzFmqiCq',
                'stripe_price' => 'price_1SqaV5RwN51ygFdeB67j70Hb',
                'name' => 'Strategic Growth Framework Creation',
                'description' => 'Fundamentos: Tu dossier comercial, Tu fachada digital, Entender tu juego',
                'category' => 'mentoring',
                'plan' => 'creation',
                'type' => 'mentoring',
                'currency' => 'eur',
                'unit_amount' => 199.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_ToCvPOhqRhdGXF',
                'stripe_product' => 'prod_ToCvPOhqRhdGXF',
                'stripe_price' => 'price_1SqaVqRwN51ygFde4jBohE1d',
                'name' => 'Strategic Growth Framework Operations',
                'description' => 'Operaciones: Tu embudo en automático, Tu embudo de operaciones, Tu business playbook, Scale framework',
                'category' => 'mentoring',
                'plan' => 'operations',
                'type' => 'mentoring',
                'currency' => 'eur',
                'unit_amount' => 299.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_ToCvJxvt9qUVWL',
                'stripe_product' => 'prod_ToCvJxvt9qUVWL',
                'stripe_price' => 'price_1SqaW2RwN51ygFdeV48zrJJ1',
                'name' => 'Strategic Growth Framework Bussiness Exit',
                'description' => 'Escalado: Simplificar tu negocio, Quitar al fundador, Crear tus managers, Generar tu cultura, Business exit',
                'category' => 'mentoring',
                'plan' => 'bussiness-exit',
                'type' => 'mentoring',
                'currency' => 'eur',
                'unit_amount' => 399.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_ToCw7vHmQqegT3',
                'stripe_product' => 'prod_ToCw7vHmQqegT3',
                'stripe_price' => 'price_1SqaWARwN51ygFde6FcSPhNE',
                'name' => 'Strategic Growth Framework Complete',
                'description' => 'Bundle completo: Incluye los 3 módulos (12 pasos)',
                'category' => 'mentoring',
                'plan' => 'complete',
                'type' => 'mentoring',
                'currency' => 'eur',
                'unit_amount' => 749.00,
                'recurring_interval' => 'year',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        // Prospecting Products (credits: Watson/Sherlock recurring, Prospection one-time)
        $prospectingProducts = [
            [
                'stripe_id' => null,
                'stripe_product' => null,
                'stripe_price' => null,
                'name' => 'Watson',
                'description' => 'Ideal para comenzar a importar prospectos',
                'category' => 'prospecting',
                'plan' => 'basic',
                'type' => 'prospecting',
                'currency' => 'eur',
                'unit_amount' => 9.99,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => null,
                'stripe_product' => null,
                'stripe_price' => null,
                'name' => 'Sherlock',
                'description' => 'Para equipos que importan muchos prospectos',
                'category' => 'prospecting',
                'plan' => 'growth',
                'type' => 'prospecting',
                'currency' => 'eur',
                'unit_amount' => 29.99,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => null,
                'stripe_product' => null,
                'stripe_price' => null,
                'name' => 'Prospection',
                'description' => 'Créditos para exportar resultados de búsqueda de prospectos (pago único)',
                'category' => 'prospecting',
                'plan' => null,
                'type' => 'prospecting',
                'currency' => 'eur',
                'unit_amount' => 100.00,
                'recurring_interval' => null,
                'recurring_interval_count' => null,
                'active' => true,
            ],
        ];

        // Hosting Products (from https://revisionalpha.com/wordpress)
        $hostingProducts = [
            [
                'stripe_id' => 'prod_ToCwLemEsmyfdB',
                'stripe_product' => 'prod_ToCwLemEsmyfdB',
                'stripe_price' => 'price_1SqaWJRwN51ygFdew8AD7i0S',
                'name' => 'WordPress Profesional',
                'description' => 'Hosting WordPress Profesional: Dominio gratis el primer año, 100 GB almacenamiento, WordPress preinstalado, SSL gratis, backups diarios',
                'category' => 'hosting',
                'plan' => 'cloud-wordPress',
                'type' => 'cloud',
                'currency' => 'eur',
                'unit_amount' => 15.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_ToCwJGi0qi7S7x',
                'stripe_product' => 'prod_ToCwJGi0qi7S7x',
                'stripe_price' => 'price_1SqaWPRwN51ygFdeviaeCVOz',
                'name' => 'WordPress Support',
                'description' => 'Servicios adicionales: Asesoramiento técnico, Optimización SEO, Adaptación móvil, Soporte vía WhatsApp, Actualización de Plugins',
                'category' => 'support',
                'plan' => 'wordpress-maintenance',
                'type' => 'support',
                'currency' => 'eur',
                'unit_amount' => 10.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        $shopProducts = [
            [
                'stripe_id' => 'prod_V89Ch5pNA8GG1s',
                'stripe_product' => 'prod_V89Ch5pNA8GG1s',
                'stripe_price' => 'price_1U7suBRwN51ygFde4qEpYyXf',
                'name' => 'Shop Freelancer',
                'description' => 'Hasta 50 productos, 50 pedidos por mes y 1 sucursal',
                'category' => 'shop',
                'plan' => 'basic',
                'type' => 'shop',
                'currency' => 'eur',
                'unit_amount' => 19.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_V89CVxlWTlBMe7',
                'stripe_product' => 'prod_V89CVxlWTlBMe7',
                'stripe_price' => 'price_1U7suDRwN51ygFdeaAni5iw4',
                'name' => 'Shop Commerce',
                'description' => 'Hasta 200 productos, 100 pedidos por mes y 3 sucursales',
                'category' => 'shop',
                'plan' => 'premium',
                'type' => 'shop',
                'currency' => 'eur',
                'unit_amount' => 39.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_V89C0HW6riezWq',
                'stripe_product' => 'prod_V89C0HW6riezWq',
                'stripe_price' => 'price_1U7suERwN51ygFdefw059YTU',
                'name' => 'Shop Enterprise',
                'description' => 'Productos y pedidos ilimitados, 5 sucursales, dominio propio y Google Analytics',
                'category' => 'shop',
                'plan' => 'profesional',
                'type' => 'shop',
                'currency' => 'eur',
                'unit_amount' => 79.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        $appProducts = [
            [
                'stripe_id' => 'prod_V89CoEJptr2nT5',
                'stripe_product' => 'prod_V89CoEJptr2nT5',
                'stripe_price' => 'price_1U7suFRwN51ygFdeFSm0geeq',
                'name' => 'Ads',
                'description' => 'Campañas de pago en Meta, Google, LinkedIn, TikTok y X',
                'category' => 'ads',
                'plan' => null,
                'type' => 'ads',
                'currency' => 'eur',
                'unit_amount' => 49.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => 'prod_V89Cx8SZW5xrV4',
                'stripe_product' => 'prod_V89Cx8SZW5xrV4',
                'stripe_price' => 'price_1U7suGRwN51ygFdeGj0PfgIh',
                'name' => 'Projects',
                'description' => 'Proyectos, tareas y entrega con clientes en un solo espacio',
                'category' => 'projects',
                'plan' => null,
                'type' => 'projects',
                'currency' => 'eur',
                'unit_amount' => 29.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => null,
                'stripe_product' => null,
                'stripe_price' => null,
                'name' => 'Affiliates',
                'description' => 'Recomendá productos Idoneo y seguí comisiones',
                'category' => 'affiliates',
                'plan' => null,
                'type' => 'affiliates',
                'currency' => 'eur',
                'unit_amount' => null,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
            [
                'stripe_id' => null,
                'stripe_product' => null,
                'stripe_price' => null,
                'name' => 'Estimator',
                'description' => 'Cotizaciones comerciales. El plan es gratis; solo se facturan los tokens de IA',
                'category' => 'estimator',
                'plan' => null,
                'type' => 'estimator',
                'currency' => 'eur',
                'unit_amount' => 0.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        $allProducts = array_merge($mailerProducts, $mentoringProducts, $prospectingProducts, $hostingProducts, $shopProducts, $appProducts);

        foreach ($allProducts as $productData)
        {
            $key = $productData['stripe_id']
                ? ['stripe_id' => $productData['stripe_id']]
                : ['category' => $productData['category'], 'plan' => $productData['plan']];

            SubscriptionProduct::updateOrCreate($key, $productData);
        }
    }
}
