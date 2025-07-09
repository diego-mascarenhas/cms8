<?php

namespace Database\Seeders;

use App\Models\Enterprise;
use Illuminate\Database\Seeder;

class EnterpriseSeeder extends Seeder
{
    public function run()
    {
        // Crear empresas básicas del sistema (necesarias para funcionamiento básico)
        $basicEnterprises = [
            [
                'team_id' => 1,
                'name' => 'Brandty',
                'type_id' => 1,
                'referred_by' => null,
                'address' => 'Calle Velázquez 27, 1º Ext. Izda.',
                'postal_code' => '28001',
                'locality' => 'Madrid',
                'province' => 'Madrid',
                'country' => 'es',
                'phone' => '+34 910 615 318',
                'whatsapp' => '+34 910 615 318',
                'email' => 'info@brandty.es',
                'website' => 'https://brandty.es',
                'data' => json_encode([]),
                'status_id' => 2,
            ],
            [
                'team_id' => 1,
                'name' => 'Generator Landing',
                'type_id' => 2,
                'referred_by' => null,
                'address' => '1234 Main St',
                'postal_code' => '90210',
                'locality' => 'Los Angeles',
                'province' => 'California',
                'country' => 'us',
                'phone' => '+1 (555) 123-4567',
                'whatsapp' => '+1 (555) 123-4567',
                'email' => 'info@generatorlanding.com',
                'website' => 'https://www.generatorlanding.com',
                'data' => json_encode([]),
                'status_id' => 2,
            ],
        ];

        // Crear empresas básicas
        $this->command->info('Creating basic enterprises...');
        foreach ($basicEnterprises as $enterprise) {
            Enterprise::create($enterprise);
        }

        // Crear empresas adicionales usando Factory
        $this->command->info('Creating additional enterprises using Factory...');

        // Crear 5 empresas de cada tipo
        $this->command->info('  ✓ Creating 5 medical/pharmaceutical enterprises...');
        Enterprise::factory()->medical()->count(5)->create();

        $this->command->info('  ✓ Creating 5 entertainment/media enterprises...');
        Enterprise::factory()->entertainment()->count(5)->create();

        $this->command->info('  ✓ Creating 5 technology/software enterprises...');
        Enterprise::factory()->technology()->count(5)->create();

        $this->command->info('  ✓ Creating 5 legal/financial enterprises...');
        Enterprise::factory()->legal()->count(5)->create();

        $this->command->info('  ✓ Creating 5 marketing/advertising enterprises...');
        Enterprise::factory()->marketing()->count(5)->create();

        $this->command->info('EnterpriseSeeder completed! Created 27 enterprises total (2 basic + 25 from Factory).');

        // Crear proyectos usando el ProjectFactory
        $this->command->info('Creating projects using ProjectFactory...');

        // Crear 30 proyectos distribuidos por tipo
        $this->command->info('  ✓ Creating 5 medical translation projects...');
        \App\Models\Project::factory()->medical()->count(5)->create();

        $this->command->info('  ✓ Creating 5 subtitle projects...');
        \App\Models\Project::factory()->subtitle()->count(5)->create();

        $this->command->info('  ✓ Creating 5 dubbing projects...');
        \App\Models\Project::factory()->dubbing()->count(5)->create();

        $this->command->info('  ✓ Creating 5 legal translation projects...');
        \App\Models\Project::factory()->legal()->count(5)->create();

        $this->command->info('  ✓ Creating 5 marketing projects...');
        \App\Models\Project::factory()->marketing()->count(5)->create();

        $this->command->info('  ✓ Creating 5 technical projects...');
        \App\Models\Project::factory()->technical()->count(5)->create();

        $this->command->info('ProjectFactory completed! Created 30 projects for team_id 1.');
    }
}
