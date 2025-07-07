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
            
            // Chinese variants (zh)
            [
                'code' => 'zh-CN',
                'name' => 'Chinese (China)',
                'base_language' => 'zh',
                'country_code' => 'CN',
            ],
            [
                'code' => 'zh-TW',
                'name' => 'Chinese (Taiwan)',
                'base_language' => 'zh',
                'country_code' => 'TW',
            ],
            
            // Japanese variants (ja)
            [
                'code' => 'ja-JP',
                'name' => 'Japanese (Japan)',
                'base_language' => 'ja',
                'country_code' => 'JP',
            ],
            
            // Korean variants (ko)
            [
                'code' => 'ko-KR',
                'name' => 'Korean (Korea)',
                'base_language' => 'ko',
                'country_code' => 'KR',
            ],
            
            // Russian variants (ru)
            [
                'code' => 'ru-RU',
                'name' => 'Russian (Russia)',
                'base_language' => 'ru',
                'country_code' => 'RU',
            ],
            
            // Arabic variants (ar)
            [
                'code' => 'ar-SA',
                'name' => 'Arabic (Saudi Arabia)',
                'base_language' => 'ar',
                'country_code' => 'SA',
            ],
            
            // Turkish variants (tr)
            [
                'code' => 'tr-TR',
                'name' => 'Turkish (Turkey)',
                'base_language' => 'tr',
                'country_code' => 'TR',
            ],
            
            // Polish variants (pl)
            [
                'code' => 'pl-PL',
                'name' => 'Polish (Poland)',
                'base_language' => 'pl',
                'country_code' => 'PL',
            ],
            
            // Swedish variants (sv)
            [
                'code' => 'sv-SE',
                'name' => 'Swedish (Sweden)',
                'base_language' => 'sv',
                'country_code' => 'SE',
            ],
            
            // Danish variants (da)
            [
                'code' => 'da-DK',
                'name' => 'Danish (Denmark)',
                'base_language' => 'da',
                'country_code' => 'DK',
            ],
            
            // Norwegian variants (nb/no)
            [
                'code' => 'nb-NO',
                'name' => 'Norwegian (Norway)',
                'base_language' => 'nb',
                'country_code' => 'NO',
            ],
            
            // Finnish variants (fi)
            [
                'code' => 'fi-FI',
                'name' => 'Finnish (Finland)',
                'base_language' => 'fi',
                'country_code' => 'FI',
            ],
            
            // Dutch variants (nl)
            [
                'code' => 'nl-NL',
                'name' => 'Dutch (Netherlands)',
                'base_language' => 'nl',
                'country_code' => 'NL',
            ],
            
            // Greek variants (el)
            [
                'code' => 'el-GR',
                'name' => 'Greek (Greece)',
                'base_language' => 'el',
                'country_code' => 'GR',
            ],
            
            // Hebrew variants (he)
            [
                'code' => 'he-IL',
                'name' => 'Hebrew (Israel)',
                'base_language' => 'he',
                'country_code' => 'IL',
            ],
            
            // Hindi variants (hi)
            [
                'code' => 'hi-IN',
                'name' => 'Hindi (India)',
                'base_language' => 'hi',
                'country_code' => 'IN',
            ],
            
            // Thai variants (th)
            [
                'code' => 'th-TH',
                'name' => 'Thai (Thailand)',
                'base_language' => 'th',
                'country_code' => 'TH',
            ],
            
            // Vietnamese variants (vi)
            [
                'code' => 'vi-VN',
                'name' => 'Vietnamese (Vietnam)',
                'base_language' => 'vi',
                'country_code' => 'VN',
            ],
            
            // Ukrainian variants (uk)
            [
                'code' => 'uk-UA',
                'name' => 'Ukrainian (Ukraine)',
                'base_language' => 'uk',
                'country_code' => 'UA',
            ],
            
            // Hungarian variants (hu)
            [
                'code' => 'hu-HU',
                'name' => 'Hungarian (Hungary)',
                'base_language' => 'hu',
                'country_code' => 'HU',
            ],
            
            // Czech variants (cs)
            [
                'code' => 'cs-CZ',
                'name' => 'Czech (Czech Republic)',
                'base_language' => 'cs',
                'country_code' => 'CZ',
            ],
            
            // Slovak variants (sk)
            [
                'code' => 'sk-SK',
                'name' => 'Slovak (Slovakia)',
                'base_language' => 'sk',
                'country_code' => 'SK',
            ],
            
            // Romanian variants (ro)
            [
                'code' => 'ro-RO',
                'name' => 'Romanian (Romania)',
                'base_language' => 'ro',
                'country_code' => 'RO',
            ],
            
            // Bulgarian variants (bg)
            [
                'code' => 'bg-BG',
                'name' => 'Bulgarian (Bulgaria)',
                'base_language' => 'bg',
                'country_code' => 'BG',
            ],
            
            // Croatian variants (hr)
            [
                'code' => 'hr-HR',
                'name' => 'Croatian (Croatia)',
                'base_language' => 'hr',
                'country_code' => 'HR',
            ],
            
            // Slovenian variants (sl)
            [
                'code' => 'sl-SI',
                'name' => 'Slovenian (Slovenia)',
                'base_language' => 'sl',
                'country_code' => 'SI',
            ],
            
            // Estonian variants (et)
            [
                'code' => 'et-EE',
                'name' => 'Estonian (Estonia)',
                'base_language' => 'et',
                'country_code' => 'EE',
            ],
            
            // Latvian variants (lv)
            [
                'code' => 'lv-LV',
                'name' => 'Latvian (Latvia)',
                'base_language' => 'lv',
                'country_code' => 'LV',
            ],
            
            // Lithuanian variants (lt)
            [
                'code' => 'lt-LT',
                'name' => 'Lithuanian (Lithuania)',
                'base_language' => 'lt',
                'country_code' => 'LT',
            ],
            
            // Maltese variants (mt)
            [
                'code' => 'mt-MT',
                'name' => 'Maltese (Malta)',
                'base_language' => 'mt',
                'country_code' => 'MT',
            ],
            
            // Basque variants (eu)
            [
                'code' => 'eu-ES',
                'name' => 'Basque (Spain)',
                'base_language' => 'eu',
                'country_code' => 'ES',
            ],
            
            // Galician variants (gl)
            [
                'code' => 'gl-ES',
                'name' => 'Galician (Spain)',
                'base_language' => 'gl',
                'country_code' => 'ES',
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
