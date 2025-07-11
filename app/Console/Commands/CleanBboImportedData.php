<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanBboImportedData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bbo:clean-imported-data
                            {--dry-run : Show what would be cleaned without making changes}
                            {--section= : Specific section to clean (fares|softwares|language_variants|services|all)}';

    /**
     * The console command description.
     */
    protected $description = 'Clean successfully imported sections from BBO contacts data field';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $section = $this->option('section') ?? 'all';

        $this->info('🧹 Starting BBO imported data cleanup...');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get BBO contacts (team_id = 4)
        $contacts = Contact::where('team_id', 4)
            ->whereNotNull('data')
            ->where('data', '!=', '{}')
            ->get();

        $this->info("Found {$contacts->count()} BBO contacts with data to process");

        $cleanedCount = 0;
        $sectionsToClean = $section === 'all' ? ['fares', 'softwares', 'language_variants', 'services'] : [$section];

        foreach ($contacts as $contact) {
            $data = is_object($contact->data) ? (array)$contact->data : ($contact->data ?? []);
            $originalData = $data;
            $cleaned = false;

            foreach ($sectionsToClean as $sectionName) {
                if (isset($data[$sectionName])) {
                    $shouldClean = false;

                    switch ($sectionName) {
                        case 'fares':
                            // Clean if contact has fare relationships
                            $shouldClean = $contact->fares()->exists();
                            break;

                        case 'softwares':
                            // Clean if contact has software relationships
                            $shouldClean = $contact->softwares()->exists();
                            break;

                        case 'language_variants':
                            // Clean if contact has language variant relationships
                            $shouldClean = $contact->languageVariants()->exists();
                            break;

                        case 'services':
                            // Always clean services as they're stored as JSON only
                            $shouldClean = !empty($data[$sectionName]);
                            break;
                    }

                    if ($shouldClean) {
                        if ($dryRun) {
                            $this->line("Would clean '{$sectionName}' from {$contact->name}");
                        } else {
                            unset($data[$sectionName]);
                            $cleaned = true;
                            $this->line("✅ Cleaned '{$sectionName}' from {$contact->name}");
                        }
                    }
                }
            }

            if ($cleaned && !$dryRun) {
                $contact->data = $data;
                $contact->save();
                $cleanedCount++;
            } elseif ($dryRun && $data !== $originalData) {
                $cleanedCount++;
            }
        }

        if ($dryRun) {
            $this->info("🔍 DRY RUN COMPLETE: Would clean data from {$cleanedCount} contacts");
            $this->info("Run without --dry-run to apply changes");
        } else {
            $this->info("✅ CLEANUP COMPLETE: Cleaned data from {$cleanedCount} contacts");
        }

        return 0;
    }
}
