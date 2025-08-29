<?php

namespace Database\Seeders;

use App\Enums\EmailPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmailPlansSeeder extends Seeder
{
    /**
     * Initialize all existing teams with BASIC email plan using team_settings
     */
    public function run(): void
    {
        $this->command->info('🚀 Initializing teams with email plans using team_settings...');

        // Get or create admin user for assignment
        $admin = User::role('admin')->first();
        if (! $admin)
        {
            $this->command->warn('⚠️  No admin user found. Creating system assignments...');
        }

        // Get teams that don't have email_plan setting configured yet
        $teams = Team::whereDoesntHave('settings', function ($query)
        {
            $query->where('key', 'email_plan')->where('group', 'email');
        })->get();

        if ($teams->isEmpty())
        {
            $this->command->info('✅ All teams already have email plans assigned');

            return;
        }

        $this->command->info("📊 Found {$teams->count()} teams without email plans");

        $basicPlan = EmailPlan::BASIC;
        $updated = 0;

        foreach ($teams as $team)
        {
            try
            {
                // Use the trait method to assign the plan (stores in team_settings)
                $team->assignEmailPlan($basicPlan, $admin?->id);

                $this->command->info("  ✅ Team #{$team->id} ({$team->name}) → {$basicPlan->getDisplayName()} plan");
                $updated++;
            } catch (\Exception $e)
            {
                $this->command->error("  ❌ Failed to assign plan to Team #{$team->id}: {$e->getMessage()}");
            }
        }

        $this->command->info("🎉 Successfully initialized {$updated} teams with email plans");

        // Show summary
        $this->command->newLine();
        $this->command->comment('📋 Email Plan Summary:');
        $this->command->table(
            ['Plan', 'Monthly Limit', 'Daily Limit', 'Contact Limit', 'Description'],
            [
                ['BASIC', '10,000', '500', '10,000', 'Ideal para comenzar'],
                ['FOUNDATION', '50,000', '2,000', '50,000', 'Para empresas en crecimiento'],
                ['SCALE', '100,000', 'Unlimited', '100,000', 'Para grandes empresas'],
            ],
        );

        $this->command->newLine();
        $this->command->comment('🔧 Storage Details:');
        $this->command->info('   • Configuration: team_settings table (group: email)');
        $this->command->info('   • Usage tracking: From message_deliveries table');
        $this->command->info('   • Contact count: From contacts table');

        $this->command->newLine();
        $this->command->comment('💡 Next Steps:');
        $this->command->info('   • Assign plans: php artisan email-plans:assign {team_id} {plan} --user={admin_id}');
        $this->command->info('   • Check limits: php artisan email-plans:check');
        $this->command->info('   • Web interface: /team/{team_id}/settings/email');
        $this->command->info('   • Admin interface: Only root users can modify limits');

        // Show sample configuration
        if ($updated > 0)
        {
            $sampleTeam = $teams->first();
            $this->command->newLine();
            $this->command->comment("📝 Sample team_settings for Team #{$sampleTeam->id}:");
            $emailSettings = $sampleTeam->settings()->where('group', 'email')->get(['key', 'value', 'type']);

            if ($emailSettings->isNotEmpty())
            {
                $this->command->table(
                    ['Setting Key', 'Value', 'Type'],
                    $emailSettings->map(function ($setting)
                    {
                        return [$setting->key, $setting->value, $setting->type];
                    })->toArray(),
                );
            }
        }
    }
}
