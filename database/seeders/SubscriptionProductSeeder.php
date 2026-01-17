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
        // Mailer Products (from https://revisionalpha.com/emailer)
        $mailerProducts = [
            [
                'name' => 'Mailer Basic',
                'description' => 'Ideal para comenzar',
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
                'name' => 'Mailer Foundation',
                'description' => 'Para empresas en crecimiento',
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
                'name' => 'Mailer Scale',
                'description' => 'Para grandes empresas',
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
                'name' => 'Strategic Growth Framework Complete',
                'description' => 'Bundle completo: Incluye los 3 módulos (12 pasos)',
                'category' => 'mentoring',
                'plan' => 'complete',
                'type' => 'mentoring',
                'currency' => 'eur',
                'unit_amount' => 749.00,
                'recurring_interval' => 'month',
                'recurring_interval_count' => 1,
                'active' => true,
            ],
        ];

        // Hosting Products (from https://revisionalpha.com/wordpress)
        $hostingProducts = [
            [
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

        $allProducts = array_merge($mailerProducts, $mentoringProducts, $hostingProducts);

        foreach ($allProducts as $productData)
        {
            SubscriptionProduct::updateOrCreate(
                [
                    'category' => $productData['category'],
                    'plan' => $productData['plan'],
                    'type' => $productData['type'],
                ],
                $productData,
            );
        }
    }
}
