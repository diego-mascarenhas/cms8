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
            ['id' => 1, 'name' => 'Inactive', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-danger'],
            ['id' => 2, 'name' => 'Active', 'enterprise_type_id' => 1, 'label_class' => 'bg-label-success'],
        ];
        
        foreach ($statuses as $status) {
            EnterpriseStatus::create($status);
        }
    }
}