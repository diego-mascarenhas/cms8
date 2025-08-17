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
        // Crear idiomas base (todos los nombres en español para consistencia)
        $languages = [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'en', 'name' => 'Inglés'],
            ['code' => 'fr', 'name' => 'Francés'],
            ['code' => 'de', 'name' => 'Alemán'],
            ['code' => 'it', 'name' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Portugués'],
            ['code' => 'ca', 'name' => 'Catalán'],
            ['code' => 'zh', 'name' => 'Chino'],
            ['code' => 'ja', 'name' => 'Japonés'],
            ['code' => 'ko', 'name' => 'Coreano'],
            ['code' => 'ru', 'name' => 'Ruso'],
            ['code' => 'ar', 'name' => 'Árabe'],
            ['code' => 'tr', 'name' => 'Turco'],
            ['code' => 'pl', 'name' => 'Polaco'],
            ['code' => 'sv', 'name' => 'Sueco'],
            ['code' => 'da', 'name' => 'Danés'],
            ['code' => 'nb', 'name' => 'Noruego'],
            ['code' => 'fi', 'name' => 'Finés'],
            ['code' => 'nl', 'name' => 'Holandés'],
            ['code' => 'el', 'name' => 'Griego'],
            ['code' => 'he', 'name' => 'Hebreo'],
            ['code' => 'hi', 'name' => 'Hindi'],
            ['code' => 'th', 'name' => 'Tailandés'],
            ['code' => 'vi', 'name' => 'Vietnamita'],
            ['code' => 'uk', 'name' => 'Ucraniano'],
            ['code' => 'hu', 'name' => 'Húngaro'],
            ['code' => 'cs', 'name' => 'Checo'],
            ['code' => 'sk', 'name' => 'Eslovaco'],
            ['code' => 'ro', 'name' => 'Rumano'],
            ['code' => 'bg', 'name' => 'Búlgaro'],
            ['code' => 'hr', 'name' => 'Croata'],
            ['code' => 'sl', 'name' => 'Esloveno'],
            ['code' => 'et', 'name' => 'Estonio'],
            ['code' => 'lv', 'name' => 'Letón'],
            ['code' => 'lt', 'name' => 'Lituano'],
            ['code' => 'mt', 'name' => 'Maltés'],
            ['code' => 'eu', 'name' => 'Euskera'],
            ['code' => 'gl', 'name' => 'Gallego'],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(
                ['code' => $language['code']],
                ['name' => $language['name']],
            );
        }
    }
}
