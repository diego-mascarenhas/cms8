<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    public function run()
    {
        $languages = [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'en', 'name' => 'Inglés'],
            ['code' => 'fr', 'name' => 'Francés'],
            ['code' => 'de', 'name' => 'Alemán'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Portugués'],
        ];

        foreach ($languages as $language) {
            Language::create($language);
        }
    }
}
