<?php

namespace App\Console\Commands;

use App\Enums\EmailPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;

class AssignEmailPlan extends Command
{
    protected $signature = 'email-plans:assign
                            {team_id? : ID of the team to assign plan to}
                            {plan? : Plan to assign (basic|foundation|scale)}
                            {--admin-id= : Admin user ID (defaults to first admin user)}
                            {--list-teams : Show all teams with current plans}';

    protected $description = 'Assign email plan to a team (only admin users can do this)';

    public function handle()
    {
        if ($this->option('list-teams'))
        {
            return $this->listTeams();
        }

        $teamId = $this->argument('team_id');
        $planValue = $this->argument('plan');
        $adminId = $this->option('admin-id');

        // Validate required arguments when not listing teams
        if (! $teamId || ! $planValue)
        {
            $this->error('❌ team_id and plan arguments are required when not using --list-teams');
            $this->info('Usage: php artisan email-plans:assign {team_id} {plan} [--admin-id=ID]');
            $this->info('   Or: php artisan email-plans:assign --list-teams');

            return 1;
        }

        // Validate plan
        try
        {
            $plan = EmailPlan::from($planValue);
        } catch (\ValueError $e)
        {
            $this->error("❌ Invalid plan: {$planValue}");
            $this->error('Valid plans: basic, foundation, scale');

            return 1;
        }

        // Find team
        $team = Team::find($teamId);
        if (! $team)
        {
            $this->error("❌ Team with ID {$teamId} not found");

            return 1;
        }

        // Get admin user
        if ($adminId)
        {
            $admin = User::find($adminId);
            if (! $admin)
            {
                $this->error("❌ Admin user with ID {$adminId} not found");

                return 1;
            }
        } else
        {
            $admin = User::role('admin')->first();
            if (! $admin)
            {
                $this->error('❌ No admin user found in the system');

                return 1;
            }
        }

        // Verify admin permissions
        if (! $admin->hasRole('admin'))
        {
            $this->error("❌ User {$admin->name} is not an admin");

            return 1;
        }

        // Show current and new plan details
        $this->info('📋 Plan Assignment Details:');
        $this->info("Team: {$team->name} (ID: {$team->id})");
        $this->info("Admin: {$admin->name} (ID: {$admin->id})");
        $this->newLine();

        // Current plan
        $currentPlan = $team->getEmailPlan();
        $this->comment("Current Plan: {$currentPlan->getDisplayName()}");
        $currentConfig = $team->getEmailPlanConfig();
        $this->table(
            ['Metric', 'Current', 'Used'],
            [
                ['Monthly Emails', number_format($currentConfig['monthly_limit']), number_format($currentConfig['monthly_used'])],
                ['Daily Emails', $currentConfig['daily_limit'] ? number_format($currentConfig['daily_limit']) : 'Unlimited', number_format($currentConfig['daily_used'])],
                ['Contacts', number_format($currentConfig['contact_limit']), number_format($team->contacts()->count())],
            ],
        );

        $this->newLine();
        $this->comment("New Plan: {$plan->getDisplayName()} - {$plan->getDescription()}");
        $newConfig = $plan->getConfig();
        $this->table(
            ['Metric', 'New Limit'],
            [
                ['Monthly Emails', number_format($newConfig['monthly_limit'])],
                ['Daily Emails', $newConfig['daily_limit'] ? number_format($newConfig['daily_limit']) : 'Unlimited'],
                ['Contacts', number_format($newConfig['contact_limit'])],
            ],
        );

        // Confirm assignment
        if (! $this->confirm("⚠️  Assign {$plan->getDisplayName()} plan to team '{$team->name}'? This will reset usage counters."))
        {
            $this->info('❌ Operation cancelled.');

            return 0;
        }

        try
        {
            // Assign the plan
            $team->assignEmailPlan($plan, $admin->id);

            $this->info("✅ Successfully assigned {$plan->getDisplayName()} plan to team '{$team->name}'");
            $this->info('📊 Usage counters have been reset');
            $this->info("👤 Assigned by: {$admin->name}");

            return 0;
        } catch (\Exception $e)
        {
            $this->error("❌ Failed to assign plan: {$e->getMessage()}");

            return 1;
        }
    }

    private function listTeams()
    {
        $teams = Team::orderBy('name')->get();

        if ($teams->isEmpty())
        {
            $this->info('📭 No teams found');

            return 0;
        }

        $this->info('📋 Teams and their Email Plans (stored in team_settings):');
        $this->newLine();

        $tableData = [];
        foreach ($teams as $team)
        {
            $plan = $team->getEmailPlan();
            $remaining = $team->getRemainingEmails();
            $contacts = $team->contacts()->count();
            $planDetails = $team->getPlanDetails();

            $tableData[] = [
                $team->id,
                $team->name,
                $plan->getDisplayName(),
                number_format($remaining['monthly_used']).'/'.number_format($remaining['monthly_limit']),
                $remaining['daily_limit']
                    ? number_format($remaining['daily_used']).'/'.number_format($remaining['daily_limit'])
                    : number_format($remaining['daily_used']).'/∞',
                number_format($contacts).'/'.number_format($team->getContactLimit()),
                $planDetails['assigned_by']?->name ?? 'System',
            ];
        }

        $this->table(
            ['ID', 'Team', 'Plan', 'Monthly', 'Daily', 'Contacts', 'Assigned By'],
            $tableData,
        );

        $this->newLine();
        $this->comment('🔧 Storage: team_settings table (group: email)');
        $this->comment('💡 To assign a plan: php artisan email-plans:assign {team_id} {plan} --admin-id={admin_id}');
        $this->comment('💡 Available plans: basic, foundation, scale');

        return 0;
    }
}
