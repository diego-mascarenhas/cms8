<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Enterprise;

class EnterpriseSeeder extends Seeder
{
    public function run()
    {
        $enterprises = [
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

        foreach ($enterprises as $enterprise) {
            Enterprise::create($enterprise);
        }
    }
}