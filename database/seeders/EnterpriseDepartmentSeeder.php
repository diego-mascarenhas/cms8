<?php

namespace Database\Seeders;

use App\Models\EnterpriseDepartment;
use Illuminate\Database\Seeder;

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
            [
                'name' => 'Publicidad',
                'color' => '#ffb3c6',
            ],
            [
                'name' => 'Medio',
                'color' => '#d4b3ff',
            ],
            [
                'name' => 'Producto',
                'color' => '#b3f0e0',
            ],
        ];

        foreach ($departments as $department)
        {
            EnterpriseDepartment::firstOrCreate(
                ['name' => $department['name']],
                $department,
            );
        }
    }
}
