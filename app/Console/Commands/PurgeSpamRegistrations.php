<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SpamRegistrationName;
use Illuminate\Console\Command;
use Laravel\Jetstream\Contracts\DeletesUsers;

class PurgeSpamRegistrations extends Command
{
    protected $signature = 'users:purge-spam-registrations {--force : Delete the matching accounts}';

    protected $description = 'Delete empty personal accounts whose name looks like a generated token';

    public function handle(DeletesUsers $deleter): int
    {
        $force = (bool) $this->option('force');
        $matched = 0;

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($deleter, $force, &$matched): void
        {
            foreach ($users as $user)
            {
                if (! SpamRegistrationName::isRandomToken((string) $user->name) || ! $this->isEmptyPersonalAccount($user))
                {
                    continue;
                }

                $matched++;

                if (! $force)
                {
                    $this->line($user->id.' '.$user->name);

                    continue;
                }

                $deleter->delete($user);
            }
        });

        $this->info($force ? 'Eliminados: '.$matched : 'Coinciden: '.$matched);

        return self::SUCCESS;
    }

    private function isEmptyPersonalAccount(User $user): bool
    {
        $teams = $user->ownedTeams()->get();

        if ($teams->count() !== 1)
        {
            return false;
        }

        $team = $teams->first();

        if (! $team->personal_team || filled($team->stripe_id))
        {
            return false;
        }

        return $team->users()->count() <= 1;
    }
}
