<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $languages = [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'Français'],
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Português'],
            ['code' => 'ca', 'name' => 'Català'],
            ['code' => 'ja', 'name' => '日本語 (Japonés)'],
            ['code' => 'zh', 'name' => '中文 (Chino)'],
            ['code' => 'ko', 'name' => '한국어 (Coreano)'],
            ['code' => 'ru', 'name' => 'Русский (Ruso)'],
            ['code' => 'ar', 'name' => 'العربية (Árabe)']
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['code' => $language['code']],
                ['name' => $language['name']]
            );
        }
    }
}
