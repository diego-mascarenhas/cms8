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
    }
}
