<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearAstralProfilesCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'astral:clear-cache {--all : Clear cache for all contacts} {contact_id? : Clear cache for specific contact ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear cached astral profiles to regenerate with updated data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all'))
        {
            return $this->clearAllProfiles();
        }

        $contactId = $this->argument('contact_id');

        if ($contactId)
        {
            return $this->clearContactProfile($contactId);
        }

        $this->error('Please specify --all or provide a contact_id');

        return Command::FAILURE;
    }

    /**
     * Clear cache for all contacts with birthday
     */
    private function clearAllProfiles(): int
    {
        $this->info('Clearing all astral profile caches...');

        $contacts = Contact::whereNotNull('birthday')->get();

        if ($contacts->isEmpty())
        {
            $this->warn('No contacts with birthday found.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($contacts->count());
        $bar->start();

        $cleared = 0;
        foreach ($contacts as $contact)
        {
            $cacheKey = "astral_profile_{$contact->id}";
            if (Cache::has($cacheKey))
            {
                Cache::forget($cacheKey);
                $cleared++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Cleared {$cleared} cached astral profiles out of {$contacts->count()} contacts with birthday.");
        $this->comment('Profiles will be regenerated on next view with updated Human Design data.');

        return Command::SUCCESS;
    }

    /**
     * Clear cache for specific contact
     */
    private function clearContactProfile($contactId): int
    {
        $contact = Contact::find($contactId);

        if (! $contact)
        {
            $this->error("Contact with ID {$contactId} not found.");

            return Command::FAILURE;
        }

        if (! $contact->birthday)
        {
            $this->warn("Contact {$contact->name} (ID: {$contactId}) has no birthday set.");

            return Command::FAILURE;
        }

        $cacheKey = "astral_profile_{$contactId}";

        if (Cache::has($cacheKey))
        {
            Cache::forget($cacheKey);
            $this->info("✅ Cleared astral profile cache for {$contact->name} (ID: {$contactId})");
            $this->comment('Profile will be regenerated on next view.');

            return Command::SUCCESS;
        }

        $this->warn("No cached profile found for {$contact->name} (ID: {$contactId})");

        return Command::SUCCESS;
    }
}
