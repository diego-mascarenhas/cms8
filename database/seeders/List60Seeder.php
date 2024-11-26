<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\List60;

class List60Seeder extends Seeder
{
    public function run()
    {
        List60::factory()->count(60)->create();
    }
}