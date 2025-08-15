<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run(): void
    {
        // Create basic template without team_id (global templates)
        Template::create([
            'name' => 'Hosting Cloud',
            'status_id' => 1,
        ]);
    }
}
