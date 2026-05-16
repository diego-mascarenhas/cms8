<?php

namespace App\Console\Commands;

use App\Mail\TeamInvitation;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestTeamInvitationEmailCommand extends Command
{
    protected $signature = 'email:test-team-invitation
                            {to : Inbox that will receive the preview}
                            {--team-id= : Team id shown in the invitation; defaults to first team}';

    protected $description = 'Send one synchronous team invitation email (emails.team-invitation) to preview layout and SMTP.';

    public function handle(): int
    {
        $to = trim((string) $this->argument('to'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL))
        {
            $this->error('Invalid recipient email.');

            return self::FAILURE;
        }

        $teamIdOption = $this->option('team-id');
        if ($teamIdOption !== null && $teamIdOption !== '')
        {
            $team = Team::query()->find((int) $teamIdOption);
            if (! $team)
            {
                $this->error("No team with id {$teamIdOption}.");

                return self::FAILURE;
            }
        } else
        {
            $team = Team::query()->orderBy('id')->first();
            if (! $team)
            {
                $this->error('No team in the database. Create a team or pass --team-id=.');

                return self::FAILURE;
            }
            $this->warn("Using team #{$team->id} ({$team->name}). Pass --team-id= to pick another.");
        }

        $invitation = $team->teamInvitations()->firstOrCreate(
            ['email' => $to],
            ['role' => 'admin'],
        );

        try
        {
            Mail::to($to)->send(new TeamInvitation($invitation));
        } catch (\Throwable $e)
        {
            $this->error('Mail send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Team invitation template sent to {$to} (team #{$team->id}: {$team->name}).");
        $this->comment('A pending invitation row was created or reused for this recipient on that team.');

        return self::SUCCESS;
    }
}
