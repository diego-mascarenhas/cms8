<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener el Team Demo (ID 1)
        $demoTeam = Team::find(1);
        if (! $demoTeam) {
            $this->command->error('Team Demo no encontrado. Asegúrate de que exista el team con ID 1.');

            return;
        }

        // Obtener categorías y monedas
        $categories = Category::all();
        $currencies = Currency::all();

        if ($categories->isEmpty()) {
            $this->command->error('No hay categorías disponibles. Ejecuta CategorySeeder primero.');

            return;
        }

        if ($currencies->isEmpty()) {
            $this->command->error('No hay monedas disponibles. Ejecuta CurrencySeeder primero.');

            return;
        }

        // Productos específicos para el Team Demo
        $products = [
            [
                'name' => 'Hosting Web Básico',
                'description' => 'Hosting web con 10GB de espacio SSD, 100GB de transferencia mensual, 5 bases de datos MySQL, 10 cuentas de email, panel cPanel, certificado SSL gratuito y soporte técnico 24/7.',
                'price' => 29.99,
                'category_id' => $categories->where('name', 'like', '%hosting%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Hosting Web Premium',
                'description' => 'Hosting web premium con 50GB de espacio SSD, transferencia ilimitada, 25 bases de datos MySQL, 100 cuentas de email, panel cPanel, certificado SSL gratuito, backup automático y soporte técnico prioritario.',
                'price' => 59.99,
                'category_id' => $categories->where('name', 'like', '%hosting%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Dominio .com',
                'description' => 'Registro de dominio .com por 1 año con protección de privacidad WHOIS, redirección de email, bloqueo de transferencia y soporte técnico.',
                'price' => 19.99,
                'category_id' => $categories->where('name', 'like', '%dominio%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Dominio .net',
                'description' => 'Registro de dominio .net por 1 año con protección de privacidad WHOIS, redirección de email, bloqueo de transferencia y soporte técnico.',
                'price' => 24.99,
                'category_id' => $categories->where('name', 'like', '%dominio%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Certificado SSL Básico',
                'description' => 'Certificado SSL básico para un dominio, válido por 1 año, con encriptación de 256 bits y soporte técnico.',
                'price' => 49.99,
                'category_id' => $categories->where('name', 'like', '%ssl%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Certificado SSL Wildcard',
                'description' => 'Certificado SSL Wildcard para un dominio y todos sus subdominios, válido por 1 año, con encriptación de 256 bits y soporte técnico.',
                'price' => 199.99,
                'category_id' => $categories->where('name', 'like', '%ssl%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Backup Automático',
                'description' => 'Servicio de backup automático diario de bases de datos y archivos, con retención de 30 días y restauración rápida.',
                'price' => 15.99,
                'category_id' => $categories->where('name', 'like', '%backup%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Desarrollo Web Básico',
                'description' => 'Desarrollo de sitio web básico con hasta 5 páginas, diseño responsive, formulario de contacto, SEO básico y 3 meses de soporte.',
                'price' => 999.99,
                'category_id' => $categories->where('name', 'like', '%desarrollo%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Desarrollo Web Premium',
                'description' => 'Desarrollo de sitio web premium con hasta 15 páginas, diseño personalizado, blog integrado, e-commerce básico, SEO avanzado y 6 meses de soporte.',
                'price' => 2499.99,
                'category_id' => $categories->where('name', 'like', '%desarrollo%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'App Móvil Básica',
                'description' => 'Desarrollo de aplicación móvil básica para iOS y Android, con hasta 5 pantallas, diseño nativo y 3 meses de soporte.',
                'price' => 1499.99,
                'category_id' => $categories->where('name', 'like', '%app%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Consultoría IT',
                'description' => 'Sesión de consultoría IT de 2 horas para análisis de infraestructura, recomendaciones de mejora y plan de implementación.',
                'price' => 199.99,
                'category_id' => $categories->where('name', 'like', '%consultoría%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Soporte Técnico Básico',
                'description' => 'Soporte técnico por email y chat para todos nuestros servicios, con tiempo de respuesta de 24 horas.',
                'price' => 79.99,
                'category_id' => $categories->where('name', 'like', '%soporte%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Soporte Técnico Premium',
                'description' => 'Soporte técnico prioritario por email, chat y teléfono, con tiempo de respuesta de 4 horas y acceso a técnicos senior.',
                'price' => 149.99,
                'category_id' => $categories->where('name', 'like', '%soporte%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Migración de Servidor',
                'description' => 'Migración completa de tu servidor actual a nuestra infraestructura, incluyendo transferencia de datos, configuración y pruebas.',
                'price' => 299.99,
                'category_id' => $categories->where('name', 'like', '%migración%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
            [
                'name' => 'Optimización SEO',
                'description' => 'Auditoría completa de SEO, optimización de contenido, mejora de velocidad, análisis de palabras clave y reporte mensual.',
                'price' => 399.99,
                'category_id' => $categories->where('name', 'like', '%seo%')->first()?->id ?? $categories->first()->id,
                'currency_id' => $currencies->where('code', 'USD')->first()?->id ?? $currencies->first()->id,
            ],
        ];

        // Crear los productos
        foreach ($products as $productData) {
            Product::create([
                ...$productData,
                'team_id' => $demoTeam->id,
                'status' => true,
                'whatsapp_enabled' => true,
            ]);
        }

        $this->command->info('Productos del Team Demo creados exitosamente.');
        $this->command->info('Total de productos creados: '.count($products));
    }
}
