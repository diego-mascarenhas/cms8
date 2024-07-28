<?php

namespace Database\Seeders;

use App\Models\EnterpriseType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnterpriseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EnterpriseType::create([
            'id' => 1,
            'name' => 'Client'
        ]);
        
        EnterpriseType::create([
            'id' => 2,
            'name' => 'Supplier'
        ]);

        EnterpriseType::create([
            'id' => 3,
            'name' => 'Partnership'
        ]);
    }
}
