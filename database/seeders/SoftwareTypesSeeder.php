<?php

namespace Database\Seeders;

use App\Models\SoftwareType;
use Illuminate\Database\Seeder;

class SoftwareTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'CMS'],
            ['name' => 'ERP'],
            ['name' => 'CRM'],
            ['name' => 'LMS'],
            ['name' => 'E-commerce'],
            ['name' => 'Project Management'],
            ['name' => 'Custom Application'],
        ];

        foreach ($types as $type) {
            SoftwareType::create($type);
        }
    }
}
