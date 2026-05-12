<?php

namespace App\Console\Commands;

use App\Mail\NewUserNotification;
use App\Models\User;
use App\Support\NewUserWelcomeEmailNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestWelcomeEmailCommand extends Command
{
    protected $signature = 'email:test-welcome
                            {to : Inbox that will receive the preview (not the context user)}
                            {--user-id= : Existing user id for template data (name, team, reset link); defaults to first non-placeholder user}';

    protected $description = 'Send one synchronous new-user welcome email (emails.new-user-notification) to preview layout and SMTP.';

    public function handle(): int
    {
        $to = trim((string) $this->argument('to'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL))
        {
            $this->error('Invalid recipient email.');

            return self::FAILURE;
        }

        $userIdOption = $this->option('user-id');
        if ($userIdOption !== null && $userIdOption !== '')
        {
            $user = User::query()->find((int) $userIdOption);
            if (! $user)
            {
                $this->error("No user with id {$userIdOption}.");

                return self::FAILURE;
            }
        } else
        {
            $user = User::query()
                ->where('email', 'not like', '%@chat.placeholder')
                ->orderBy('id')
                ->first();
            if (! $user)
            {
                $this->error('No suitable user in the database. Create a user or pass --user-id=.');

                return self::FAILURE;
            }
            $this->warn("Using context user #{$user->id} ({$user->email}). Pass --user-id= to pick another.");
        }

        if (NewUserWelcomeEmailNotifier::isPlaceholderInboxEmail($user->email))
        {
            $this->error('Context user has a synthetic @chat.placeholder email; pick another user with --user-id=.');

            return self::FAILURE;
        }

        try
        {
            Mail::to($to)->send(new NewUserNotification($user, $user->currentTeam));
        } catch (\Throwable $e)
        {
            $this->error('Mail send failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $teamId = $user->currentTeam?->id ?? 'none';
        $this->info("Welcome template sent to {$to} (context: user #{$user->id}, team {$teamId}).");
        $this->comment('Note: a new password-reset token was created for the context user in the database.');

        return self::SUCCESS;
    }
}
