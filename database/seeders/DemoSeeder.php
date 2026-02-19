<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

/**
 * Demo seed — complementary to the main deploy seed.
 * Run after deploy (or after db:seed) to create demo clients and projects for testing.
 * Does not run inside DatabaseSeeder; use: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::withoutGlobalScopes()
            ->where('name', "REVISION ALPHA's Team")
            ->first();

        if (! $team)
        {
            $this->command->warn('Revision Alpha team not found. Run the main seed first: php artisan db:seed');

            return;
        }

        DemoDataService::createClientsAndProjects($team->id, $this->command);
    }
}
