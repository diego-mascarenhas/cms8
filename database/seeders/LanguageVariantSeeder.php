<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LanguageVariant;
use Illuminate\Support\Facades\DB;

class LanguageVariantSeeder extends Seeder
{
    public function run()
    {
        $variants = [
            // Español
            [
                'code' => 'es_ES',
                'name' => 'Español (España)',
                'base_language' => 'es',
                'country_code' => 'es',
                'native_name' => 'Español (España)',
                'flag' => 'es'
            ],
            [
                'code' => 'es_AR',
                'name' => 'Español (Argentina)',
                'base_language' => 'es',
                'country_code' => 'ar',
                'native_name' => 'Español (Argentina)',
                'flag' => 'ar'
            ],
            [
                'code' => 'es_MX',
                'name' => 'Español (México)',
                'base_language' => 'es',
                'country_code' => 'mx',
                'native_name' => 'Español (México)',
                'flag' => 'mx'
            ],
            [
                'code' => 'es_CO',
                'name' => 'Español (Colombia)',
                'base_language' => 'es',
                'country_code' => 'co',
                'native_name' => 'Español (Colombia)',
                'flag' => 'co'
            ],

            // Inglés
            [
                'code' => 'en_US',
                'name' => 'Inglés (Estados Unidos)',
                'base_language' => 'en',
                'country_code' => 'us',
                'native_name' => 'English (United States)',
                'flag' => 'us'
            ],
            [
                'code' => 'en_GB',
                'name' => 'Inglés (Reino Unido)',
                'base_language' => 'en',
                'country_code' => 'gb',
                'native_name' => 'English (United Kingdom)',
                'flag' => 'gb'
            ],

            // Francés
            [
                'code' => 'fr_FR',
                'name' => 'Francés (Francia)',
                'base_language' => 'fr',
                'country_code' => 'fr',
                'native_name' => 'Français (France)',
                'flag' => 'fr'
            ],
            [
                'code' => 'fr_CA',
                'name' => 'Francés (Canadá)',
                'base_language' => 'fr',
                'country_code' => 'ca',
                'native_name' => 'Français (Canada)',
                'flag' => 'ca'
            ],

            // Alemán
            [
                'code' => 'de_DE',
                'name' => 'Alemán (Alemania)',
                'base_language' => 'de',
                'country_code' => 'de',
                'native_name' => 'Deutsch (Deutschland)',
                'flag' => 'de'
            ],

            // Italiano
            [
                'code' => 'it_IT',
                'name' => 'Italiano (Italia)',
                'base_language' => 'it',
                'country_code' => 'it',
                'native_name' => 'Italiano',
                'flag' => 'it'
            ],

            // Portugués
            [
                'code' => 'pt_PT',
                'name' => 'Portugués (Portugal)',
                'base_language' => 'pt',
                'country_code' => 'pt',
                'native_name' => 'Português (Portugal)',
                'flag' => 'pt'
            ],
            [
                'code' => 'pt_BR',
                'name' => 'Portugués (Brasil)',
                'base_language' => 'pt',
                'country_code' => 'br',
                'native_name' => 'Português (Brasil)',
                'flag' => 'br'
            ],
        ];

        foreach ($variants as $variant)
        {
            LanguageVariant::create($variant);
        }
    }
}