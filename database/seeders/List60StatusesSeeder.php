<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\List60Status;

class List60StatusesSeeder extends Seeder
{
    public function run()
    {
        List60Status::create(['name' => '1 Contact', 'label_class' => 'bg-label-success']);
        List60Status::create(['name' => '2 Contact', 'label_class' => 'bg-label-warning']);
        List60Status::create(['name' => '3 Contact', 'label_class' => 'bg-label-danger']);
        List60Status::create(['name' => 'Stopped', 'label_class' => 'bg-label-secondary']);
        List60Status::create(['name' => 'No Answer', 'label_class' => 'bg-label-info']);
    }
}