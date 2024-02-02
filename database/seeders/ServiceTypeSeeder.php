<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ServiceType::create([
            'id' => 1,
            'name' => 'cPanel Hosting'
        ]);

        ServiceType::create([
            'id' => 2,
            'name' => 'cPanel Cloud'
        ]);

        ServiceType::create([
            'id' => 4,
            'name' => 'Mailer'
        ]);
    }
}