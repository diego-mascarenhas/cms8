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
    public function run()
    {
        Category::create([
            'id' => 5000,
            'name' => 'Messages',
            'parent_id' => null,
            'status' => 1
        ]);

        Category::create([
            'id' => 5001,
            'name' => 'Tester',
            'parent_id' => 5000,
            'status' => 1
        ]);

        Category::create([
            'id' => 5002,
            'name' => 'Prospect',
            'parent_id' => 5000,
            'status' => 0
        ]);

        Category::create([
            'id' => 5003,
            'name' => 'Demo',
            'parent_id' => 5000,
            'status' => 1
        ]);

        Category::create([
            'id' => 5004,
            'name' => 'Staff',
            'parent_id' => 5000,
            'status' => 1
        ]);
    }
}
