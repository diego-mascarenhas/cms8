<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDemoProducts();
    }

    /**
     * Seed demo products for Team 1
     */
    private function seedDemoProducts(): void
    {
        $this->command->info('🛍️ Creating demo products for Team 1...');

        // Ensure User 1 exists
        $user = \App\Models\User::find(1);
        if (! $user) {
            $this->command->warn('⚠️ User 1 does not exist. Creating demo user...');
            $user = \App\Models\User::create([
                'id' => 1,
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $this->command->info("✅ Created user: {$user->name}");
        }

        // Ensure Team 1 exists
        $team = \App\Models\Team::find(1);
        if (! $team) {
            $this->command->warn('⚠️ Team 1 does not exist. Creating demo team...');
            $team = \App\Models\Team::create([
                'id' => 1,
                'user_id' => $user->id,
                'name' => 'Demo Team',
                'personal_team' => false,
            ]);
            $this->command->info("✅ Created team: {$team->name}");
        }

        // Ensure we have categories and currencies
        $categories = Category::where('team_id', 1)->get();
        $currencies = \App\Models\Currency::all();

        if ($categories->isEmpty()) {
            $this->command->warn('⚠️ No categories found for Team 1. Creating default category...');
            $defaultCategory = Category::create([
                'team_id' => 1,
                'name' => 'Technology Services',
                'description' => 'Technology and software services',
            ]);
            $categories = collect([$defaultCategory]);
        }

        if ($currencies->isEmpty()) {
            $this->command->warn('⚠️ No currencies found. Creating default currencies...');
            $this->call(\Database\Seeders\CurrencySeeder::class);
            $currencies = \App\Models\Currency::all();
        }

        // Get USD currency (or first available)
        $usdCurrency = $currencies->where('code', 'USD')->first() ?? $currencies->first();
        $eurCurrency = $currencies->where('code', 'EUR')->first() ?? $currencies->first();

        // Create demo products
        $products = [
            [
                'name' => 'Hosting Web Básico',
                'description' => 'Hosting web con 10GB de espacio SSD, 100GB de transferencia mensual, 5 bases de datos MySQL, 10 cuentas de email, panel cPanel, certificado SSL gratuito y soporte técnico 24/7.',
                'price' => 29.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%hosting%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Hosting Web Premium',
                'description' => 'Hosting web premium con 50GB de espacio SSD, transferencia ilimitada, 25 bases de datos MySQL, 100 cuentas de email, panel cPanel, certificado SSL gratuito, backup automático y soporte técnico prioritario.',
                'price' => 59.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%hosting%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Dominio .com',
                'description' => 'Registro de dominio .com por 1 año con protección de privacidad WHOIS, redirección de email, bloqueo de transferencia y soporte técnico.',
                'price' => 19.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%dominio%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Certificado SSL Básico',
                'description' => 'Certificado SSL básico para un dominio, válido por 1 año, con encriptación de 256 bits y soporte técnico.',
                'price' => 49.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%ssl%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Desarrollo Web Básico',
                'description' => 'Desarrollo de sitio web básico con hasta 5 páginas, diseño responsive, formulario de contacto, SEO básico y 3 meses de soporte.',
                'price' => 999.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%desarrollo%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'App Móvil Básica',
                'description' => 'Desarrollo de aplicación móvil básica para iOS y Android, con hasta 5 pantallas, diseño nativo y 3 meses de soporte.',
                'price' => 1499.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%app%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Consultoría IT',
                'description' => 'Sesión de consultoría IT de 2 horas para análisis de infraestructura, recomendaciones de mejora y plan de implementación.',
                'price' => 199.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%consultoría%')->first()?->id ?? $categories->first()->id,
            ],
            [
                'name' => 'Soporte Técnico Premium',
                'description' => 'Soporte técnico prioritario por email, chat y teléfono, con tiempo de respuesta de 4 horas y acceso a técnicos senior.',
                'price' => 149.99,
                'currency_id' => $usdCurrency->id,
                'category_id' => $categories->where('name', 'like', '%soporte%')->first()?->id ?? $categories->first()->id,
            ],
        ];

        $created = 0;
        foreach ($products as $productData) {
            // Check if product already exists
            $existingProduct = \App\Models\Product::where('name', $productData['name'])
                ->where('team_id', 1)
                ->first();

            if (! $existingProduct) {
                \App\Models\Product::create([
                    ...$productData,
                    'team_id' => 1,
                    'status' => true,
                    'whatsapp_enabled' => true,
                ]);
                $created++;
                $this->command->info("✅ Created product: {$productData['name']}");
            } else {
                $this->command->info("⏭️ Skipped existing product: {$productData['name']}");
            }
        }

        $total = \App\Models\Product::where('team_id', 1)->count();
        $this->command->info('📊 Demo products summary:');
        $this->command->info("   - New products created: {$created}");
        $this->command->info("   - Total products for Team 1: {$total}");
        $this->command->info('✅ Demo products creation completed successfully!');
    }
}
