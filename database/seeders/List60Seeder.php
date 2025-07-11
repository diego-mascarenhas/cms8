<?php

namespace Database\Seeders;

use App\Models\List60;
use Illuminate\Database\Seeder;

class List60Seeder extends Seeder
{
    public function run()
    {
        List60::factory()->count(60)->create();
    }
}
