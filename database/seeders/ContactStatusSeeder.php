<?php

namespace Database\Seeders;

use App\Models\ContactStatus;
use Illuminate\Database\Seeder;

class ContactStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Lead', 'label_class' => 'bg-label-info'],
            ['name' => 'Follow Up', 'label_class' => 'bg-label-warning'],
            ['name' => 'Conversion', 'label_class' => 'bg-label-info'],
            ['name' => 'Close', 'label_class' => 'bg-label-info'],
            ['name' => 'Active', 'label_class' => 'bg-label-success'],
            ['name' => 'Lost', 'label_class' => 'bg-label-danger'],
            ['name' => 'Finished', 'label_class' => 'bg-label-danger'],
        ];
        
        foreach ($statuses as $status) {
            ContactStatus::create($status);
        }
    }
}