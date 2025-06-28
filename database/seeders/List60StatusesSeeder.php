<?php

namespace Database\Seeders;

use App\Models\List60Status;
use Illuminate\Database\Seeder;

class List60StatusesSeeder extends Seeder
{
    public function run()
    {
        List60Status::create(['name' => '1 Contacto', 'label_class' => 'bg-label-success']);
        List60Status::create(['name' => '2 Contactos', 'label_class' => 'bg-label-warning']);
        List60Status::create(['name' => '3 Contactos', 'label_class' => 'bg-label-danger']);
        List60Status::create(['name' => 'Parado', 'label_class' => 'bg-label-secondary']);
        List60Status::create(['name' => 'Sin respuesta', 'label_class' => 'bg-label-info']);
    }
}
