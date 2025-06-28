<?php

namespace Database\Seeders;

use App\Models\LanguageVariant;
use Illuminate\Database\Seeder;

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
            'code' => 'es-ES',
        ], [
            'name' => 'Español (España)',
            'base_language' => 'es',
            'country_code' => 'ES',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'es-MX',
        ], [
            'name' => 'Español (México)',
            'base_language' => 'es',
            'country_code' => 'MX',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'es-AR',
        ], [
            'name' => 'Español (Argentina)',
            'base_language' => 'es',
            'country_code' => 'AR',
        ]);

        // English variants
        LanguageVariant::firstOrCreate([
            'code' => 'en-US',
        ], [
            'name' => 'English (United States)',
            'base_language' => 'en',
            'country_code' => 'US',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'en-GB',
        ], [
            'name' => 'English (United Kingdom)',
            'base_language' => 'en',
            'country_code' => 'GB',
        ]);

        // French variants
        LanguageVariant::firstOrCreate([
            'code' => 'fr-FR',
        ], [
            'name' => 'Français (France)',
            'base_language' => 'fr',
            'country_code' => 'FR',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'fr-CA',
        ], [
            'name' => 'Français (Canada)',
            'base_language' => 'fr',
            'country_code' => 'CA',
        ]);

        // German variants
        LanguageVariant::firstOrCreate([
            'code' => 'de-DE',
        ], [
            'name' => 'Deutsch (Deutschland)',
            'base_language' => 'de',
            'country_code' => 'DE',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'de-AT',
        ], [
            'name' => 'Deutsch (Österreich)',
            'base_language' => 'de',
            'country_code' => 'AT',
        ]);

        // Portuguese variants
        LanguageVariant::firstOrCreate([
            'code' => 'pt-PT',
        ], [
            'name' => 'Português (Portugal)',
            'base_language' => 'pt',
            'country_code' => 'PT',
        ]);

        LanguageVariant::firstOrCreate([
            'code' => 'pt-BR',
        ], [
            'name' => 'Português (Brasil)',
            'base_language' => 'pt',
            'country_code' => 'BR',
        ]);

        // Italian
        LanguageVariant::firstOrCreate([
            'code' => 'it-IT',
        ], [
            'name' => 'Italiano (Italia)',
            'base_language' => 'it',
            'country_code' => 'IT',
        ]);

        // Catalan
        LanguageVariant::firstOrCreate([
            'code' => 'ca-ES',
        ], [
            'name' => 'Català (Espanya)',
            'base_language' => 'ca',
            'country_code' => 'ES',
        ]);
    }
}
