<?php

namespace Database\Seeders;

use App\Models\PromptType;
use Illuminate\Database\Seeder;

class PromptTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PromptType::create([
            'id' => 1,
            'name' => 'Commercial',
        ]);

        PromptType::create([
            'id' => 2,
            'name' => 'Support',
        ]);
    }
}