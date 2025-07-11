<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\LanguageVariant;
use Illuminate\Database\Seeder;

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
            ['code' => 'zh', 'name' => 'Chinese'],
            ['code' => 'ja', 'name' => 'Japanese'],
            ['code' => 'ko', 'name' => 'Korean'],
            ['code' => 'ru', 'name' => 'Russian'],
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'tr', 'name' => 'Turkish'],
            ['code' => 'pl', 'name' => 'Polish'],
            ['code' => 'sv', 'name' => 'Swedish'],
            ['code' => 'da', 'name' => 'Danish'],
            ['code' => 'nb', 'name' => 'Norwegian'],
            ['code' => 'fi', 'name' => 'Finnish'],
            ['code' => 'nl', 'name' => 'Dutch'],
            ['code' => 'el', 'name' => 'Greek'],
            ['code' => 'he', 'name' => 'Hebrew'],
            ['code' => 'hi', 'name' => 'Hindi'],
            ['code' => 'th', 'name' => 'Thai'],
            ['code' => 'vi', 'name' => 'Vietnamese'],
            ['code' => 'uk', 'name' => 'Ukrainian'],
            ['code' => 'hu', 'name' => 'Hungarian'],
            ['code' => 'cs', 'name' => 'Czech'],
            ['code' => 'sk', 'name' => 'Slovak'],
            ['code' => 'ro', 'name' => 'Romanian'],
            ['code' => 'bg', 'name' => 'Bulgarian'],
            ['code' => 'hr', 'name' => 'Croatian'],
            ['code' => 'sl', 'name' => 'Slovenian'],
            ['code' => 'et', 'name' => 'Estonian'],
            ['code' => 'lv', 'name' => 'Latvian'],
            ['code' => 'lt', 'name' => 'Lithuanian'],
            ['code' => 'mt', 'name' => 'Maltese'],
            ['code' => 'eu', 'name' => 'Basque'],
            ['code' => 'gl', 'name' => 'Galician'],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                ['name' => $language['name']],
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
            ['code' => 'gl-ES', 'name' => 'Galego-España', 'base_language' => 'gl', 'country_code' => 'ES'],
        ];

        foreach ($variants as $variant) {
            LanguageVariant::updateOrCreate(
                ['code' => $variant['code']],
                [
                    'name' => $variant['name'],
                    'base_language' => $variant['base_language'],
                    'country_code' => $variant['country_code'],
                ],
            );
        }
    }
}
