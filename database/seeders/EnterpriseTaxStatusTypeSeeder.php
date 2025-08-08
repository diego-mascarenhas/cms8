<?php

namespace Database\Seeders;

use App\Models\EnterpriseTaxStatusType;
use Illuminate\Database\Seeder;

class EnterpriseTaxStatusTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Exempt from tax',
            'Registered taxpayer',
            'Self-employed',
            'Tax-exempt entity',
            'VAT registered',
            'Non-resident taxpayer',
            'Corporate taxpayer',
            'Individual taxpayer',
        ];

        foreach ($types as $name) {
            EnterpriseTaxStatusType::updateOrCreate(
                ['name' => $name],
                []
            );
        }

        $this->command->info('EnterpriseTaxStatusType seeded successfully.');
    }
}


