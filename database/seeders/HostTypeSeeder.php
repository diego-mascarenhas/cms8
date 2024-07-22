<?php

namespace Database\Seeders;

use App\Models\HostType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HostTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HostType::create([
            'id' => 1,
            'name' => 'Host'
        ]);
        
        HostType::create([
            'id' => 2,
            'name' => 'VM'
        ]);

        
    }
}
