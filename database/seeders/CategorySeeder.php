<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating basic system categories...');
        
        // Basic system categories that don't depend on modules or teams
        // These are used by core functionality and should exist before users are created
        
        // Messages categories (used by UserSeeder)
        $messagesParent = Category::create([
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
        
        // Services main category
        Category::create([
            'id' => 4000,
            'name' => 'Services',
            'parent_id' => null,
            'status' => 1
        ]);
        
        $this->command->info('Basic system categories created successfully.');
    }
}
