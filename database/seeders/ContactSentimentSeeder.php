<?php

namespace Database\Seeders;

use App\Models\ContactSentiment;
use Illuminate\Database\Seeder;

class ContactSentimentSeeder extends Seeder
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

        foreach ($sentiments as $sentiment)
        {
            ContactSentiment::updateOrCreate(
                ['id' => $sentiment['id']],
                $sentiment,
            );
        }
    }
}
