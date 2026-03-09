<?php

namespace Database\Seeders;

use App\Enums\EmailPlan;
use App\Enums\ProspectPlan;
use App\Models\Enterprise;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamHumanoSeeder extends Seeder
{
    private $teamId = 3;  // Humano Team ID

    public function run()
    {
        $this->command->info('🚀 Setting up Humano Data...');

        // 1. Create Humano Team
        $team = $this->createHumanoTeam();

        // 2. Create Humano users
        $this->createHumanoUsers($team);

        // 3. Mailer and Prospect credits for testing
        $this->configureTeamCreditsForTesting($team);

        // 4. Create Humano enterprise
        //  $this->createHumanoEnterprise($team);

        // 5. Create Humano contacts
        // $this->createHumanoContacts($team);

        // 6. Create Humano categories
        // $this->createHumanoCategories();

        // 7. Assign core modules to team
        $this->assignCoreModules($team);

        $this->command->info('✅ Humano setup completed successfully');
    }

    /**
     * Create Humano Team
     */
    private function createHumanoTeam()
    {
        $humanoOwner = User::where('email', 'victor@machbel.com')->first();

        // Create Humano owner if not exists
        if (! $humanoOwner)
        {
            $humanoOwner = User::create([
                'name' => 'Victor Machbel',
                'email' => 'victor@machbel.com',
                'password' => Hash::make('Simplicity!'),
            ]);
            $humanoOwner->assignRole('admin');
            $this->command->info('✅ Created Humano owner user: victor@machbel.com');
        }

        // Use Jetstream's proper method to create team
        $team = $humanoOwner->ownedTeams()->firstOrCreate(
            ['name' => "Humano's Team"],
            [
                'name' => "Humano's Team",
                'personal_team' => false,
            ],
        );

        // Ensure known password for owner
        $humanoOwner->update(['password' => Hash::make('Simplicity!')]);

        // Ensure the user is in the team
        if (! $team->users()->where('user_id', $humanoOwner->id)->exists())
        {
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
        $this->command->info('👥 Setting up Humano users...');

        $humanoOwner = User::where('email', 'victor@machbel.com')->first();

        // Update current team for main user (already created in createHumanoTeam)
        $humanoOwner->update([
            'current_team_id' => $team->id,
        ]);

        // Add revision alpha user to humano team as well (create if missing)
        $revision = User::where('email', 'diego.mascarenhas@icloud.com')->first();
        if (! $revision)
        {
            $revision = User::create([
                'name' => 'Diego Mascarenhas',
                'email' => 'diego.mascarenhas@icloud.com',
                'password' => Hash::make('Simplicity!'),
            ]);
            $revision->assignRole('admin');
            $this->command->info('✅ Created user: diego.mascarenhas@icloud.com');
        }

        // Always set known password for Diego
        $revision->update(['password' => Hash::make('Simplicity!')]);

        // Ensure user is in team and set as current team
        if (! $revision->teams()->where('team_id', $team->id)->exists())
        {
            $revision->teams()->attach($team->id, ['role' => 'admin']);
        }
        $revision->update(['current_team_id' => $team->id]);
        $this->command->info('✅ Added Diego Mascarenhas to Humano team');

        $this->command->info('✅ Updated Humano team users');
    }

    /**
     * Assign core modules to Humano team
     */
    private function assignCoreModules($team)
    {
        $this->command->info('📦 Assigning core modules to Humano team...');

        // Define default active module keys for Humano
        $defaultModuleKeys = [
            'contacts',  // Contact management
            'enterprises',  // Enterprise management
            'prospecting',  // Prospect search
            'services',  // Service management
            'projects',  // Project management
            'tasks',  // Task management
            'times',  // Time tracking
            'invoices',  // Invoice management
            'payments',  // Payment management
            'attendances',  // Attendance tracking
            'collaborators',  // Team collaboration
            'notifications',  // Notification system
        ];

        // Get all modules by their keys
        $modules = Module::whereIn('key', $defaultModuleKeys)->get();

        if ($modules->isEmpty())
        {
            $this->command->warn('⚠️  No modules found with the specified keys');

            return;
        }

        // Attach modules to team (using sync to avoid duplicates)
        $moduleIds = $modules->pluck('id')->toArray();
        $team->modules()->syncWithoutDetaching($moduleIds);

        $this->command->info("✅ Assigned {$modules->count()} modules to Humano team");

        // Display assigned modules
        foreach ($modules as $module)
        {
            $this->command->line("   • {$module->name} ({$module->key})");
        }
    }

    /**
     * Assign Mailer and Prospect credits for testing.
     */
    private function configureTeamCreditsForTesting(Team $team): void
    {
        $this->command->info('📧 Configuring Mailer and Prospect credits for testing...');
        $team->assignEmailPlan(EmailPlan::BASIC, null);
        $team->assignProspectPlan(ProspectPlan::BASIC, null);
        $team->addProspectCreditsFromPurchase(50);
        $this->command->info('✅ Mailer (Basic) and Prospect (Basic + 50 credits) configured');
    }
}
