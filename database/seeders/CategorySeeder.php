<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'id' => 1,
            'name' => 'Tester',
            'status' => 1
        ]);

        Category::create([
            'id' => 2,
            'name' => 'Prospect',
            'status' => 0
        ]);

        Category::create([
            'id' => 3,
            'name' => 'Demo',
            'status' => 1
        ]);

        Category::create([
            'id' => 4,
            'name' => 'Staff',
            'status' => 1
        ]);
    }
}
