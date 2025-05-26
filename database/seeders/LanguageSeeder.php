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
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['code' => $language['code']],
                ['name' => $language['name']]
            );
        }
    }
}
