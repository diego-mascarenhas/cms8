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
				'name' => 'Inglés (Estados Unidos)',
				'base_language' => 'en',
				'country_code' => 'US',
			],
			[
				'code' => 'en-GB',
				'name' => 'Inglés (Reino Unido)',
				'base_language' => 'en',
				'country_code' => 'GB',
			],
			[
				'code' => 'en-CA',
				'name' => 'Inglés (Canadá)',
				'base_language' => 'en',
				'country_code' => 'CA',
			],
			[
				'code' => 'en-AU',
				'name' => 'Inglés (Australia)',
				'base_language' => 'en',
				'country_code' => 'AU',
			],

			// Spanish variants (es)
			[
				'code' => 'es-ES',
				'name' => 'Español (España)',
				'base_language' => 'es',
				'country_code' => 'ES',
			],
			[
				'code' => 'es-MX',
				'name' => 'Español (México)',
				'base_language' => 'es',
				'country_code' => 'MX',
			],
			[
				'code' => 'es-AR',
				'name' => 'Español (Argentina)',
				'base_language' => 'es',
				'country_code' => 'AR',
			],
			[
				'code' => 'es-CO',
				'name' => 'Español (Colombia)',
				'base_language' => 'es',
				'country_code' => 'CO',
			],
			[
				'code' => 'es-CL',
				'name' => 'Español (Chile)',
				'base_language' => 'es',
				'country_code' => 'CL',
			],
			[
				'code' => 'es-PE',
				'name' => 'Español (Perú)',
				'base_language' => 'es',
				'country_code' => 'PE',
			],
			[
				'code' => 'es-VE',
				'name' => 'Español (Venezuela)',
				'base_language' => 'es',
				'country_code' => 'VE',
			],

			// French variants (fr)
			[
				'code' => 'fr-FR',
				'name' => 'Francés (Francia)',
				'base_language' => 'fr',
				'country_code' => 'FR',
			],
			[
				'code' => 'fr-CA',
				'name' => 'Francés (Canadá)',
				'base_language' => 'fr',
				'country_code' => 'CA',
			],
			[
				'code' => 'fr-BE',
				'name' => 'Francés (Bélgica)',
				'base_language' => 'fr',
				'country_code' => 'BE',
			],
			[
				'code' => 'fr-CH',
				'name' => 'Francés (Suiza)',
				'base_language' => 'fr',
				'country_code' => 'CH',
			],

			// German variants (de)
			[
				'code' => 'de-DE',
				'name' => 'Alemán (Alemania)',
				'base_language' => 'de',
				'country_code' => 'DE',
			],
			[
				'code' => 'de-AT',
				'name' => 'Alemán (Austria)',
				'base_language' => 'de',
				'country_code' => 'AT',
			],
			[
				'code' => 'de-CH',
				'name' => 'Alemán (Suiza)',
				'base_language' => 'de',
				'country_code' => 'CH',
			],

			// Italian variants (it)
			[
				'code' => 'it-IT',
				'name' => 'Italiano (Italia)',
				'base_language' => 'it',
				'country_code' => 'IT',
			],
			[
				'code' => 'it-CH',
				'name' => 'Italiano (Suiza)',
				'base_language' => 'it',
				'country_code' => 'CH',
			],

			// Portuguese variants (pt)
			[
				'code' => 'pt-PT',
				'name' => 'Portugués (Portugal)',
				'base_language' => 'pt',
				'country_code' => 'PT',
			],
			[
				'code' => 'pt-BR',
				'name' => 'Portugués (Brasil)',
				'base_language' => 'pt',
				'country_code' => 'BR',
			],

			// Catalan variants (ca)
			[
				'code' => 'ca-ES',
				'name' => 'Catalán (España)',
				'base_language' => 'ca',
				'country_code' => 'ES',
			],
			[
				'code' => 'ca-AD',
				'name' => 'Catalán (Andorra)',
				'base_language' => 'ca',
				'country_code' => 'AD',
			],

			// Chinese variants (zh)
			[
				'code' => 'zh-CN',
				'name' => 'Chino (China)',
				'base_language' => 'zh',
				'country_code' => 'CN',
			],
			[
				'code' => 'zh-TW',
				'name' => 'Chino (Taiwán)',
				'base_language' => 'zh',
				'country_code' => 'TW',
			],

			// Japanese variants (ja)
			[
				'code' => 'ja-JP',
				'name' => 'Japonés (Japón)',
				'base_language' => 'ja',
				'country_code' => 'JP',
			],

			// Korean variants (ko)
			[
				'code' => 'ko-KR',
				'name' => 'Coreano (Corea)',
				'base_language' => 'ko',
				'country_code' => 'KR',
			],

			// Russian variants (ru)
			[
				'code' => 'ru-RU',
				'name' => 'Ruso (Rusia)',
				'base_language' => 'ru',
				'country_code' => 'RU',
			],

			// Arabic variants (ar)
			[
				'code' => 'ar-SA',
				'name' => 'Árabe (Arabia Saudita)',
				'base_language' => 'ar',
				'country_code' => 'SA',
			],

			// Turkish variants (tr)
			[
				'code' => 'tr-TR',
				'name' => 'Turco (Turquía)',
				'base_language' => 'tr',
				'country_code' => 'TR',
			],

			// Polish variants (pl)
			[
				'code' => 'pl-PL',
				'name' => 'Polaco (Polonia)',
				'base_language' => 'pl',
				'country_code' => 'PL',
			],

			// Swedish variants (sv)
			[
				'code' => 'sv-SE',
				'name' => 'Sueco (Suecia)',
				'base_language' => 'sv',
				'country_code' => 'SE',
			],

			// Danish variants (da)
			[
				'code' => 'da-DK',
				'name' => 'Danés (Dinamarca)',
				'base_language' => 'da',
				'country_code' => 'DK',
			],

			// Norwegian variants (nb/no)
			[
				'code' => 'nb-NO',
				'name' => 'Noruego (Noruega)',
				'base_language' => 'nb',
				'country_code' => 'NO',
			],

			// Finnish variants (fi)
			[
				'code' => 'fi-FI',
				'name' => 'Finlandés (Finlandia)',
				'base_language' => 'fi',
				'country_code' => 'FI',
			],

			// Dutch variants (nl)
			[
				'code' => 'nl-NL',
				'name' => 'Neerlandés (Países Bajos)',
				'base_language' => 'nl',
				'country_code' => 'NL',
			],

			// Greek variants (el)
			[
				'code' => 'el-GR',
				'name' => 'Griego (Grecia)',
				'base_language' => 'el',
				'country_code' => 'GR',
			],

			// Hebrew variants (he)
			[
				'code' => 'he-IL',
				'name' => 'Hebreo (Israel)',
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
				'name' => 'Tailandés (Tailandia)',
				'base_language' => 'th',
				'country_code' => 'TH',
			],

			// Vietnamese variants (vi)
			[
				'code' => 'vi-VN',
				'name' => 'Vietnamita (Vietnam)',
				'base_language' => 'vi',
				'country_code' => 'VN',
			],

			// Ukrainian variants (uk)
			[
				'code' => 'uk-UA',
				'name' => 'Ucraniano (Ucrania)',
				'base_language' => 'uk',
				'country_code' => 'UA',
			],

			// Hungarian variants (hu)
			[
				'code' => 'hu-HU',
				'name' => 'Húngaro (Hungría)',
				'base_language' => 'hu',
				'country_code' => 'HU',
			],

			// Czech variants (cs)
			[
				'code' => 'cs-CZ',
				'name' => 'Checo (República Checa)',
				'base_language' => 'cs',
				'country_code' => 'CZ',
			],

			// Slovak variants (sk)
			[
				'code' => 'sk-SK',
				'name' => 'Eslovaco (Eslovaquia)',
				'base_language' => 'sk',
				'country_code' => 'SK',
			],

			// Romanian variants (ro)
			[
				'code' => 'ro-RO',
				'name' => 'Rumano (Rumanía)',
				'base_language' => 'ro',
				'country_code' => 'RO',
			],

			// Bulgarian variants (bg)
			[
				'code' => 'bg-BG',
				'name' => 'Búlgaro (Bulgaria)',
				'base_language' => 'bg',
				'country_code' => 'BG',
			],

			// Croatian variants (hr)
			[
				'code' => 'hr-HR',
				'name' => 'Croata (Croacia)',
				'base_language' => 'hr',
				'country_code' => 'HR',
			],

			// Slovenian variants (sl)
			[
				'code' => 'sl-SI',
				'name' => 'Esloveno (Eslovenia)',
				'base_language' => 'sl',
				'country_code' => 'SI',
			],

			// Estonian variants (et)
			[
				'code' => 'et-EE',
				'name' => 'Estonio (Estonia)',
				'base_language' => 'et',
				'country_code' => 'EE',
			],

			// Latvian variants (lv)
			[
				'code' => 'lv-LV',
				'name' => 'Letón (Letonia)',
				'base_language' => 'lv',
				'country_code' => 'LV',
			],

			// Lithuanian variants (lt)
			[
				'code' => 'lt-LT',
				'name' => 'Lituano (Lituania)',
				'base_language' => 'lt',
				'country_code' => 'LT',
			],

			// Maltese variants (mt)
			[
				'code' => 'mt-MT',
				'name' => 'Maltés (Malta)',
				'base_language' => 'mt',
				'country_code' => 'MT',
			],

			// Basque variants (eu)
			[
				'code' => 'eu-ES',
				'name' => 'Euskera (España)',
				'base_language' => 'eu',
				'country_code' => 'ES',
			],

			// Galician variants (gl)
			[
				'code' => 'gl-ES',
				'name' => 'Gallego (España)',
				'base_language' => 'gl',
				'country_code' => 'ES',
			],
		];

		foreach ($languageVariants as $variant)
		{
			LanguageVariant::firstOrCreate(
				['code' => $variant['code']],
				$variant
			);
		}

		$this->command->info('Language variants seeded successfully.');
	}
}
