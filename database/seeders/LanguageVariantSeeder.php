<?php

namespace Database\Seeders;

use App\Models\LanguageVariant;
use Illuminate\Database\Seeder;

class LanguageVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languageVariants = [
            // English variants (en)
            [
                'code' => 'en-US',
                'name' => 'English (United States)',
                'base_language' => 'en',
                'country_code' => 'US',
            ],
            [
                'code' => 'en-GB',
                'name' => 'English (United Kingdom)',
                'base_language' => 'en',
                'country_code' => 'GB',
            ],
            [
                'code' => 'en-CA',
                'name' => 'English (Canada)',
                'base_language' => 'en',
                'country_code' => 'CA',
            ],
            [
                'code' => 'en-AU',
                'name' => 'English (Australia)',
                'base_language' => 'en',
                'country_code' => 'AU',
            ],
            
            // Spanish variants (es)
            [
                'code' => 'es-ES',
                'name' => 'Spanish (Spain)',
                'base_language' => 'es',
                'country_code' => 'ES',
            ],
            [
                'code' => 'es-MX',
                'name' => 'Spanish (Mexico)',
                'base_language' => 'es',
                'country_code' => 'MX',
            ],
            [
                'code' => 'es-AR',
                'name' => 'Spanish (Argentina)',
                'base_language' => 'es',
                'country_code' => 'AR',
            ],
            [
                'code' => 'es-CO',
                'name' => 'Spanish (Colombia)',
                'base_language' => 'es',
                'country_code' => 'CO',
            ],
            [
                'code' => 'es-CL',
                'name' => 'Spanish (Chile)',
                'base_language' => 'es',
                'country_code' => 'CL',
            ],
            [
                'code' => 'es-PE',
                'name' => 'Spanish (Peru)',
                'base_language' => 'es',
                'country_code' => 'PE',
            ],
            [
                'code' => 'es-VE',
                'name' => 'Spanish (Venezuela)',
                'base_language' => 'es',
                'country_code' => 'VE',
            ],
            
            // French variants (fr)
            [
                'code' => 'fr-FR',
                'name' => 'French (France)',
                'base_language' => 'fr',
                'country_code' => 'FR',
            ],
            [
                'code' => 'fr-CA',
                'name' => 'French (Canada)',
                'base_language' => 'fr',
                'country_code' => 'CA',
            ],
            [
                'code' => 'fr-BE',
                'name' => 'French (Belgium)',
                'base_language' => 'fr',
                'country_code' => 'BE',
            ],
            [
                'code' => 'fr-CH',
                'name' => 'French (Switzerland)',
                'base_language' => 'fr',
                'country_code' => 'CH',
            ],
            
            // German variants (de)
            [
                'code' => 'de-DE',
                'name' => 'German (Germany)',
                'base_language' => 'de',
                'country_code' => 'DE',
            ],
            [
                'code' => 'de-AT',
                'name' => 'German (Austria)',
                'base_language' => 'de',
                'country_code' => 'AT',
            ],
            [
                'code' => 'de-CH',
                'name' => 'German (Switzerland)',
                'base_language' => 'de',
                'country_code' => 'CH',
            ],
            
            // Italian variants (it)
            [
                'code' => 'it-IT',
                'name' => 'Italian (Italy)',
                'base_language' => 'it',
                'country_code' => 'IT',
            ],
            [
                'code' => 'it-CH',
                'name' => 'Italian (Switzerland)',
                'base_language' => 'it',
                'country_code' => 'CH',
            ],
            
            // Portuguese variants (pt)
            [
                'code' => 'pt-PT',
                'name' => 'Portuguese (Portugal)',
                'base_language' => 'pt',
                'country_code' => 'PT',
            ],
            [
                'code' => 'pt-BR',
                'name' => 'Portuguese (Brazil)',
                'base_language' => 'pt',
                'country_code' => 'BR',
            ],
            
            // Catalan variants (ca)
            [
                'code' => 'ca-ES',
                'name' => 'Catalan (Spain)',
                'base_language' => 'ca',
                'country_code' => 'ES',
            ],
            [
                'code' => 'ca-AD',
                'name' => 'Catalan (Andorra)',
                'base_language' => 'ca',
                'country_code' => 'AD',
            ],
        ];

        foreach ($languageVariants as $variant) {
            LanguageVariant::firstOrCreate(
                ['code' => $variant['code']],
                $variant
            );
        }

        $this->command->info('Language variants seeded successfully.');
    }
}
