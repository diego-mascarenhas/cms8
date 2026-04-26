<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Services\AdminProactiveWhatsAppOutreachExecutor;
use Illuminate\Console\Command;

/**
 * Send a proactive WhatsApp opening using the team flow for a keyword (default: demo — instructivo / onboarding).
 */
class HumanoSendDemoWhatsAppCommand extends Command
{
    protected $signature = 'humano:send-demo
                            {phone : E.164 or digits, e.g. +34600111222}
                            {--team= : Team ID (required)}
                            {--user= : User ID to act as (must be admin/root on that team; default: team owner)}
                            {--keyword=demo : Flow keyword matching an active team prompt (section key, routing key, or label)}';

    protected $description = 'Send proactive WhatsApp outreach (same as /enviar-demo in chat or WhatsApp). Forced team flow + opening message';

    public function handle(AdminProactiveWhatsAppOutreachExecutor $executor): int
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

        $phoneRaw = trim((string) $this->argument('phone'));
        $digits = preg_replace('/[^0-9]/', '', $phoneRaw) ?? '';
        if ($digits === '')
        {
            $this->error('Invalid phone: no digits found.');

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

        $keyword = trim((string) $this->option('keyword'));
        if ($keyword === '')
        {
            $this->error('Keyword must not be empty.');

            return self::FAILURE;
        }

        $persistLine = 'humano:send-demo '.$keyword.' '.$phoneRaw;

        $result = $executor->execute($actor, $team, $keyword, $digits, $persistLine);

        if (! ($result['success'] ?? false))
        {
            $this->error((string) ($result['message'] ?? 'Failed.'));

            return self::FAILURE;
        }

        $this->info((string) ($result['response'] ?? 'Done.'));
        $this->line('Routing key: '.(string) ($result['routing_key'] ?? ''));
        $this->line('Phone: '.(string) ($result['phone'] ?? ''));
        $this->line('Sent via tool: '.((($result['sent_via_tool'] ?? false) === true) ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
