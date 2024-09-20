<?php

namespace Database\Seeders;

use App\Models\EnterpriseSentiment;
use Illuminate\Database\Seeder;

class EnterpriseSentimentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sentiments = [
            ['id' => 1, 'name' => 'Muy Negativo'],
            ['id' => 2, 'name' => 'Negativo'],
            ['id' => 3, 'name' => 'Neutral'],
            ['id' => 4, 'name' => 'Positivo'],
            ['id' => 5, 'name' => 'Muy Positivo'],
        ];

        foreach ($sentiments as $sentiment) {
            EnterpriseSentiment::create($sentiment);
        }
    }
}