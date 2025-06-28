<?php

namespace Database\Seeders;

use App\Models\FareBlock;
use Illuminate\Database\Seeder;

class FareBlocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $blocks = [
            ['name' => 'Traducción'],
            ['name' => 'Subtitulado'],
            ['name' => 'Audiodescripción'],
            ['name' => 'Otras tarifas'],
        ];

        foreach ($blocks as $block) {
            FareBlock::create($block);
        }
    }
}
