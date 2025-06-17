<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;
use App\Models\LanguageVariant;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear idiomas base
        $languages = [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'Français'],
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Português'],
            ['code' => 'ca', 'name' => 'Català'],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                ['name' => $language['name']]
            );
        }

        // Crear variantes de idiomas
        $variants = [
            ['code' => 'es-ES', 'name' => 'Español-España', 'base_language' => 'es', 'country_code' => 'ES'],
            ['code' => 'es-MX', 'name' => 'Español-México', 'base_language' => 'es', 'country_code' => 'MX'],
            ['code' => 'es-AR', 'name' => 'Español-Argentina', 'base_language' => 'es', 'country_code' => 'AR'],
            ['code' => 'en-US', 'name' => 'English-United States', 'base_language' => 'en', 'country_code' => 'US'],
            ['code' => 'en-GB', 'name' => 'English-United Kingdom', 'base_language' => 'en', 'country_code' => 'GB'],
            ['code' => 'fr-FR', 'name' => 'Français-France', 'base_language' => 'fr', 'country_code' => 'FR'],
            ['code' => 'fr-CA', 'name' => 'Français-Canada', 'base_language' => 'fr', 'country_code' => 'CA'],
            ['code' => 'de-DE', 'name' => 'Deutsch-Deutschland', 'base_language' => 'de', 'country_code' => 'DE'],
            ['code' => 'it-IT', 'name' => 'Italiano-Italia', 'base_language' => 'it', 'country_code' => 'IT'],
            ['code' => 'pt-PT', 'name' => 'Português-Portugal', 'base_language' => 'pt', 'country_code' => 'PT'],
            ['code' => 'pt-BR', 'name' => 'Português-Brasil', 'base_language' => 'pt', 'country_code' => 'BR'],
            ['code' => 'ca-ES', 'name' => 'Català-España', 'base_language' => 'ca', 'country_code' => 'ES'],
        ];

        foreach ($variants as $variant) {
            LanguageVariant::updateOrCreate(
                ['code' => $variant['code']],
                [
                    'name' => $variant['name'],
                    'base_language' => $variant['base_language'],
                    'country_code' => $variant['country_code']
                ]
            );
        }
    }
}
