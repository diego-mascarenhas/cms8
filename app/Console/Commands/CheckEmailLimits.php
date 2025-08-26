<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class CheckEmailLimits extends Command
{
    protected $signature = 'email-plans:check
                            {--team-id= : Check specific team only}
                            {--over-limits : Show only teams that are over limits}
                            {--reset-limits : Reset daily/monthly limits if needed}
                            {--format=table : Output format (table|json)}';

    protected $description = 'Check email usage and limits for teams';

    public function handle()
    {
        $teamId = $this->option('team-id');
        $overLimitsOnly = $this->option('over-limits');
        $resetLimits = $this->option('reset-limits');
        $format = $this->option('format');

        // Get teams
        $query = Team::query();

        if ($teamId)
        {
            $query->where('id', $teamId);
        }

        $teams = $query->orderBy('name')->get();

        if ($teams->isEmpty())
        {
            $this->info('📭 No teams found');

            return 0;
        }

        $data = [];
        $overLimitCount = 0;

        foreach ($teams as $team)
        {
            if ($resetLimits)
            {
                $team->resetLimitsIfNeeded();
            }

            $plan = $team->getEmailPlan();
            $remaining = $team->getRemainingEmails();
            $limits = $team->isOverLimits();
            $contacts = $team->contacts()->count();
            $planDetails = $team->getPlanDetails();

            $teamData = [
                'id' => $team->id,
                'name' => $team->name,
                'plan' => $plan->getDisplayName(),
                'monthly_used' => $remaining['monthly_used'],
                'monthly_limit' => $remaining['monthly_limit'],
                'monthly_remaining' => $remaining['monthly_remaining'],
                'monthly_percentage' => $remaining['monthly_limit'] > 0
                    ? round(($remaining['monthly_used'] / $remaining['monthly_limit']) * 100, 1)
                    : 0,
                'daily_used' => $remaining['daily_used'],
                'daily_limit' => $remaining['daily_limit'],
                'daily_remaining' => $remaining['daily_remaining'],
                'daily_percentage' => $remaining['daily_limit']
                    ? round(($remaining['daily_used'] / $remaining['daily_limit']) * 100, 1)
                    : null,
                'contacts_count' => $contacts,
                'contact_limit' => $team->getContactLimit(),
                'contact_percentage' => round(($contacts / $team->getContactLimit()) * 100, 1),
                'over_monthly' => $limits['over_monthly'],
                'over_daily' => $limits['over_daily'],
                'over_contacts' => $limits['over_contacts'],
                'can_send' => $limits['can_send'],
                'assigned_by' => $planDetails['assigned_by']?->name ?? 'System',
                'reset_at' => [
                    'monthly' => $planDetails['monthly_reset_at']?->format('Y-m-d H:i'),
                    'daily' => $planDetails['daily_reset_date'],
                ],
                'storage' => 'team_settings',
            ];

            if ($limits['over_monthly'] || $limits['over_daily'] || $limits['over_contacts'])
            {
                $overLimitCount++;
            }

            if (! $overLimitsOnly || $limits['over_monthly'] || $limits['over_daily'] || $limits['over_contacts'])
            {
                $data[] = $teamData;
            }
        }

        // Output results
        if ($format === 'json')
        {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return 0;
        }

        // Table format
        $this->info('📊 Email Usage Report');
        if ($resetLimits)
        {
            $this->info('🔄 Limits have been reset where needed');
        }
        $this->newLine();

        if (empty($data))
        {
            $this->info('✅ All teams are within their limits!');

            return 0;
        }

        // Summary
        $this->comment('Teams checked: '.$teams->count());
        if ($overLimitCount > 0)
        {
            $this->warn("⚠️  Teams over limits: {$overLimitCount}");
        } else
        {
            $this->info('✅ All teams within limits');
        }
        $this->newLine();

        // Detailed table
        $tableData = [];
        foreach ($data as $team)
        {
            $monthlyStatus = $team['over_monthly'] ? '🔴' : ($team['monthly_percentage'] > 80 ? '🟡' : '🟢');
            $dailyStatus = $team['over_daily'] ? '🔴' : ($team['daily_percentage'] && $team['daily_percentage'] > 80 ? '🟡' : '🟢');
            $contactStatus = $team['over_contacts'] ? '🔴' : ($team['contact_percentage'] > 80 ? '🟡' : '🟢');

            $tableData[] = [
                $team['id'],
                $team['name'],
                $team['plan'],
                $monthlyStatus.' '.number_format($team['monthly_used']).'/'.number_format($team['monthly_limit']).' ('.$team['monthly_percentage'].'%)',
                $team['daily_limit']
                    ? $dailyStatus.' '.number_format($team['daily_used']).'/'.number_format($team['daily_limit']).' ('.($team['daily_percentage'] ?? 0).'%)'
                    : '🟢 '.number_format($team['daily_used']).'/∞',
                $contactStatus.' '.number_format($team['contacts_count']).'/'.number_format($team['contact_limit']).' ('.$team['contact_percentage'].'%)',
                $team['can_send'] ? '✅' : '❌',
            ];
        }

        $this->table(
            ['ID', 'Team', 'Plan', 'Monthly Usage', 'Daily Usage', 'Contacts', 'Can Send'],
            $tableData,
        );

        $this->newLine();
        $this->comment('🟢 = Good | 🟡 = Warning (>80%) | 🔴 = Over Limit');

        if ($overLimitCount > 0)
        {
            $this->newLine();
            $this->warn('⚠️  Teams over limits need attention:');
            foreach ($data as $team)
            {
                if ($team['over_monthly'] || $team['over_daily'] || $team['over_contacts'])
                {
                    $issues = [];
                    if ($team['over_monthly'])
                    {
                        $issues[] = 'monthly emails';
                    }
                    if ($team['over_daily'])
                    {
                        $issues[] = 'daily emails';
                    }
                    if ($team['over_contacts'])
                    {
                        $issues[] = 'contacts';
                    }

                    $this->warn("  • {$team['name']}: Over ".implode(', ', $issues));
                }
            }
        }

        return 0;
    }
}
