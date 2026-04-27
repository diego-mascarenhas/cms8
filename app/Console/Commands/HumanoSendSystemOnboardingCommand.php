<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Services\SystemOnboardingWhatsAppService;
use Illuminate\Console\Command;

class HumanoSendSystemOnboardingCommand extends Command
{
    protected $signature = 'humano:system-onboarding
                            {phone : E.164 or digits, e.g. +34600111222}
                            {--team= : Team ID (required)}
                            {--user= : User ID to act as (must be admin/root on that team; default: team owner)}';

    protected $description = 'Send fixed reseller system onboarding by WhatsApp (text + hardcoded images)';

    public function handle(SystemOnboardingWhatsAppService $service): int
    {
        $teamOption = $this->option('team');
        if ($teamOption === null || $teamOption === '')
        {
            $this->error('The --team=TEAM_ID option is required.');

            return self::FAILURE;
        }

        $teamId = (int) $teamOption;
        if ($teamId < 1)
        {
            $this->error('Invalid --team value.');

            return self::FAILURE;
        }

        $team = Team::withoutGlobalScopes()->find($teamId);
        if (! $team)
        {
            $this->error("No team with id {$teamId}.");

            return self::FAILURE;
        }

        $userOption = $this->option('user');
        if ($userOption !== null && $userOption !== '')
        {
            $actor = User::withoutGlobalScopes()->find((int) $userOption);
            if (! $actor)
            {
                $this->error('User not found for --user=.');

                return self::FAILURE;
            }
        } else
        {
            $actor = User::withoutGlobalScopes()->find((int) $team->user_id);
            if (! $actor)
            {
                $this->error('Team has no owner user; pass --user= explicitly.');

                return self::FAILURE;
            }
        }

        $phoneRaw = trim((string) $this->argument('phone'));
        $result = $service->execute($actor, $team, $phoneRaw, 'cli');

        if (! ($result['success'] ?? false))
        {
            $this->error((string) ($result['message'] ?? 'Failed.'));

            return self::FAILURE;
        }

        $this->info((string) ($result['response'] ?? 'Done.'));
        $this->line('Phone: '.(string) ($result['phone'] ?? ''));
        $this->line('Media sent: '.(string) ($result['sent_media_count'] ?? 0));

        $missing = $result['missing_media'] ?? [];
        if (is_array($missing) && $missing !== [])
        {
            $this->warn('Missing/unavailable media: '.implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}
