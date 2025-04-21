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
        // $messagesParent = Category::create([
        //     'id' => 5000,
        //     'name' => 'Messages',
        //     'parent_id' => null,
        //     'status' => 1
        // ]);
        
        // Category::create([
        //     'id' => 5001,
        //     'name' => 'Tester',
        //     'parent_id' => 5000,
        //     'status' => 1
        // ]);
        
        // Category::create([
        //     'id' => 5002,
        //     'name' => 'Prospect',
        //     'parent_id' => 5000,
        //     'status' => 0
        // ]);
        
        // Category::create([
        //     'id' => 5003,
        //     'name' => 'Demo',
        //     'parent_id' => 5000,
        //     'status' => 1
        // ]);
        
        // Category::create([
        //     'id' => 5004,
        //     'name' => 'Staff',
        //     'parent_id' => 5000,
        //     'status' => 1
        // ]);
        
        // // Services main category
        // Category::create([
        //     'id' => 1,
        //     'name' => 'Services',
        //     'parent_id' => null,
        //     'status' => 1
        // ]);

        // OVH service categories as subcategories
        Category::create([
            'id' => 401,
            'name' => 'VPS (Virtual Private Server)',
            'description' => 'Virtual servers for hosting applications and websites',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 402,
            'name' => 'Web Hosting',
            'description' => 'Shared hosting solutions for websites',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 403,
            'name' => 'Domain Names',
            'description' => 'Domain name registration and management',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 404,
            'name' => 'DNS Zones',
            'description' => 'DNS management for domains',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 405,
            'name' => 'Email Domain',
            'description' => 'Email services attached to domains',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 406,
            'name' => 'Email Pro',
            'description' => 'Professional email hosting solutions',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 407,
            'name' => 'cPanel License',
            'description' => 'Control panel licenses for web hosting management',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 408,
            'name' => 'Private Database',
            'description' => 'Dedicated database servers',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 409,
            'name' => 'Cloud Project',
            'description' => 'Infrastructure as a Service cloud platform',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);

        Category::create([
            'id' => 410,
            'name' => 'vRack',
            'description' => 'Private virtual network',
            'module_id' => 9,
            'team_id' => null,
            'parent_id' => 1,
            'status' => 1
        ]);
        
        $this->command->info('Basic system categories created successfully.');
    }
}
