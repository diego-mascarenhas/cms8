<?php

namespace Database\Seeders;

use App\Models\List60Status;
use Illuminate\Database\Seeder;

class List60StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => '1 Contacto', 'label_class' => 'bg-label-success'],
            ['name' => '2 Contactos', 'label_class' => 'bg-label-warning'],
            ['name' => '3 Contactos', 'label_class' => 'bg-label-danger'],
            ['name' => 'Parado', 'label_class' => 'bg-label-secondary'],
            ['name' => 'Sin respuesta', 'label_class' => 'bg-label-info'],
        ];
        foreach ($statuses as $status)
        {
            List60Status::firstOrCreate(
                ['name' => $status['name']],
                $status,
            );
        }
    }
}
