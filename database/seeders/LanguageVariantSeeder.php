<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LanguageVariant;
use App\Models\Language;

class LanguageVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Spanish variants
        LanguageVariant::firstOrCreate([
            'code' => 'es-ES'
        ], [
            'name' => 'Español (España)',
            'base_language' => 'es',
            'country_code' => 'ES',
            'native_name' => 'Español (España)',
            'flag' => 'ES'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'es-MX'
        ], [
            'name' => 'Español (México)',
            'base_language' => 'es',
            'country_code' => 'MX',
            'native_name' => 'Español (México)',
            'flag' => 'MX'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'es-AR'
        ], [
            'name' => 'Español (Argentina)',
            'base_language' => 'es',
            'country_code' => 'AR',
            'native_name' => 'Español (Argentina)',
            'flag' => 'AR'
        ]);

        // English variants
        LanguageVariant::firstOrCreate([
            'code' => 'en-US'
        ], [
            'name' => 'English (United States)',
            'base_language' => 'en',
            'country_code' => 'US',
            'native_name' => 'English (United States)',
            'flag' => 'US'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'en-GB'
        ], [
            'name' => 'English (United Kingdom)',
            'base_language' => 'en',
            'country_code' => 'GB',
            'native_name' => 'English (United Kingdom)',
            'flag' => 'GB'
        ]);

        // French variants
        LanguageVariant::firstOrCreate([
            'code' => 'fr-FR'
        ], [
            'name' => 'Français (France)',
            'base_language' => 'fr',
            'country_code' => 'FR',
            'native_name' => 'Français (France)',
            'flag' => 'FR'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'fr-CA'
        ], [
            'name' => 'Français (Canada)',
            'base_language' => 'fr',
            'country_code' => 'CA',
            'native_name' => 'Français (Canada)',
            'flag' => 'CA'
        ]);

        // German variants
        LanguageVariant::firstOrCreate([
            'code' => 'de-DE'
        ], [
            'name' => 'Deutsch (Deutschland)',
            'base_language' => 'de',
            'country_code' => 'DE',
            'native_name' => 'Deutsch (Deutschland)',
            'flag' => 'DE'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'de-AT'
        ], [
            'name' => 'Deutsch (Österreich)',
            'base_language' => 'de',
            'country_code' => 'AT',
            'native_name' => 'Deutsch (Österreich)',
            'flag' => 'AT'
        ]);

        // Portuguese variants
        LanguageVariant::firstOrCreate([
            'code' => 'pt-PT'
        ], [
            'name' => 'Português (Portugal)',
            'base_language' => 'pt',
            'country_code' => 'PT',
            'native_name' => 'Português (Portugal)',
            'flag' => 'PT'
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'pt-BR'
        ], [
            'name' => 'Português (Brasil)',
            'base_language' => 'pt',
            'country_code' => 'BR',
            'native_name' => 'Português (Brasil)',
            'flag' => 'BR'
        ]);

        // Italian
        LanguageVariant::firstOrCreate([
            'code' => 'it-IT'
        ], [
            'name' => 'Italiano (Italia)',
            'base_language' => 'it',
            'country_code' => 'IT',
            'native_name' => 'Italiano (Italia)',
            'flag' => 'IT'
        ]);

        // Catalan
        LanguageVariant::firstOrCreate([
            'code' => 'ca-ES'
        ], [
            'name' => 'Català (Espanya)',
            'base_language' => 'ca',
            'country_code' => 'ES',
            'native_name' => 'Català (Espanya)',
            'flag' => 'ES'
        ]);
    }
}