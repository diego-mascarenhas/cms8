<?php

namespace Database\Seeders;

use App\Models\FareType;
use Illuminate\Database\Seeder;

class FareBlocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blocks = [
            ['id' => 1, 'name' => 'Traducción'],
            ['id' => 2, 'name' => 'Subtitulado'],
            ['id' => 3, 'name' => 'Audiodescripción'],
        ];

        foreach ($blocks as $block) {
            FareType::create($block);
        }
    }
}
