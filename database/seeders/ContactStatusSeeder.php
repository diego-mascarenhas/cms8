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
            ['name' => 'Lead', 'label_class' => 'bg-label-success'],
            ['name' => 'En seguimiento', 'label_class' => 'bg-label-warning'],
            ['name' => 'Conversión', 'label_class' => 'bg-label-info'],
            ['name' => 'Perdido', 'label_class' => 'bg-label-danger'],
            ['name' => 'Cliente', 'label_class' => 'bg-label-primary'],
            ['name' => 'Finalizado', 'label_class' => 'bg-label-dark'],
        ];

        foreach ($statuses as $status)
        {
            ContactStatus::firstOrCreate(
                ['name' => $status['name']],
                $status,
            );
        }
    }
}
