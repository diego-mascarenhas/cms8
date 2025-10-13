<?php

namespace Database\Seeders;

use App\Models\Stylebook;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StylebooksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stylebooks = [
            [
                'name' => 'APA Style Guide',
                'language' => 'en',
                'date' => Carbon::now()->subMonths(3),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'Chicago Manual of Style',
                'language' => 'en',
                'date' => Carbon::now()->subMonths(6),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'MLA Style Guide',
                'language' => 'en',
                'date' => Carbon::now()->subMonths(9),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'Manual de Estilo El País',
                'language' => 'es',
                'date' => Carbon::now()->subMonths(1),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'Guía de Estilo - RAE',
                'language' => 'es',
                'date' => Carbon::now()->subMonths(5),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'Le Petit Robert Style Guide',
                'language' => 'fr',
                'date' => Carbon::now()->subMonths(2),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
            [
                'name' => 'Duden Style Guide',
                'language' => 'de',
                'date' => Carbon::now()->subMonths(4),
                'file' => 'stylebooks/placeholder.pdf',
                'team_id' => 1,
            ],
        ];

        // Create the storage directory if it doesn't exist
        if (! Storage::disk('public')->exists('stylebooks'))
        {
            Storage::disk('public')->makeDirectory('stylebooks');
        }

        // Create a placeholder PDF file if it doesn't exist
        $placeholderPath = 'stylebooks/placeholder.pdf';
        if (! Storage::disk('public')->exists($placeholderPath))
        {
            Storage::disk('public')->put($placeholderPath, 'Placeholder file for seeding purposes');
        }

        foreach ($stylebooks as $stylebook)
        {
            Stylebook::create($stylebook);
        }
    }
}
