<?php

namespace Database\Seeders;

use App\Models\EnterpriseStatus;
use Illuminate\Database\Seeder;

class EnterpriseStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Lead', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-info'],
            ['name' => 'Conversion', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-info'],
            ['name' => 'Close', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-info'],
            ['name' => 'Follow Up', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-warning'],
            ['name' => 'Lost', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-danger'],
            ['name' => 'Active', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-success'],
        ];
        
        foreach ($statuses as $status) {
            EnterpriseStatus::create($status);
        }
    }
}