<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HumanoSeeder extends Seeder
{
    private $teamId = 3; // Humano Team ID

    public function run()
    {
        $this->command->info('🚀 Setting up Humano Data...');

        // 1. Create Humano Team
        $team = $this->createHumanoTeam();

        // 2. Create Humano users
        $this->createHumanoUsers($team);

        // 3. Create Humano enterprise
       //  $this->createHumanoEnterprise($team);

        // 4. Create Humano contacts
        // $this->createHumanoContacts($team);

        // 5. Create Humano categories
        // $this->createHumanoCategories();

        $this->command->info('✅ Humano setup completed successfully');
    }

    /**
     * Create Humano Team
     */
    private function createHumanoTeam()
    {
        $humanoOwner = User::where('email', 'victor@machbel.com')->first();

        if (!$humanoOwner) {
            $this->command->error('Humano owner user not found. Please run UserSeeder first.');
            return null;
        }

        $team = Team::updateOrCreate(
            ['name' => "Humano's Team"],
            [
                'user_id' => $humanoOwner->id,
                'name' => "Humano's Team",
                'personal_team' => false,
            ]
        );

        // Ensure the user is in the team
        if (!$team->users()->where('user_id', $humanoOwner->id)->exists()) {
            $team->users()->attach($humanoOwner->id, ['role' => 'admin']);
        }

        $this->command->info("✅ Created Humano Team (ID: {$team->id})");

        return $team;
    }

    /**
     * Create Humano users
     */
    private function createHumanoUsers($team)
    {
        $this->command->info('👥 Creating Humano users...');

        $humanoOwner = User::where('email', 'victor@machbel.com')->first();

        // Update current team for main user
        $humanoOwner->update(['current_team_id' => $team->id]);

        // Add revision alpha user to humano team as well
        $revision = User::where('email', 'diego.mascarenhas@icloud.com')->first();
        if ($revision) {
            if (!$revision->teams()->where('team_id', $team->id)->exists()) {
                $revision->teams()->attach($team->id, ['role' => 'admin']);
            }
            $this->command->info("✅ Added Diego Mascarenhas to Humano team");
        }

        $this->command->info("✅ Updated Humano team users");
    }
}
