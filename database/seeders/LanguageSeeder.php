<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        $languages = [
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'zh', 'name' => 'Chinese (Simplified)'],
            ['code' => 'nl', 'name' => 'Dutch'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'hi', 'name' => 'Hindi'],
            ['code' => 'it', 'name' => 'Italian'],
            ['code' => 'ja', 'name' => 'Japanese'],
            ['code' => 'ko', 'name' => 'Korean'],
            ['code' => 'pl', 'name' => 'Polish'],
            ['code' => 'pt', 'name' => 'Portuguese'],
            ['code' => 'ru', 'name' => 'Russian'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'sv', 'name' => 'Swedish'],
        ];

        foreach ($languages as $language) {
            Language::create([
                'code' => $language['code'],
                'name' => $language['name'],
            ]);
        }
    }
}