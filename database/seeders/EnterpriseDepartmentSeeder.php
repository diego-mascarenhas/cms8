<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EnterpriseDepartment;

class EnterpriseDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Administración',
                'color' => '#feff9c',
            ],
            [
                'name' => 'Técnica',
                'color' => '#ffc988',
            ],
            [
                'name' => 'Comercial',
                'color' => '#b4ff88',
            ],
            [
                'name' => 'Desarrollo',
                'color' => '#88e1ff',
            ],
        ];

        foreach ($departments as $department) {
            EnterpriseDepartment::create($department);
        }
    }
}