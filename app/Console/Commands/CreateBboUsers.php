<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateBboUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bbo:create-users
                            {--dry-run : Show what would be created without making changes}
                            {--limit=50 : Maximum number of users to create}';

    /**
     * The console command description.
     */
    protected $description = 'Create users for BBO contacts that have email but no associated user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('🔍 Finding BBO contacts with email but no user...');

        // Find BBO contacts with email but no user
        $contactsQuery = Contact::where('team_id', 4)
            ->whereNotNull('email')
            ->whereNull('user_id')
            ->orderBy('name');

        $totalContacts = $contactsQuery->count();
        $contacts = $contactsQuery->limit($limit)->get();

        $this->info("Found {$totalContacts} contacts without users (processing {$contacts->count()})");

        if ($contacts->isEmpty()) {
            $this->info('✅ No contacts found that need users created');
            return 0;
        }

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->table(
                ['Name', 'Email', 'Team ID'],
                $contacts->map(function ($contact) {
                    return [
                        $contact->name,
                        $contact->email,
                        $contact->team_id,
                    ];
                })->toArray()
            );
            return 0;
        }

        $this->info('👥 Creating users for BBO contacts...');
        $bar = $this->output->createProgressBar($contacts->count());
        $bar->start();

        $created = 0;
        $errors = 0;

        foreach ($contacts as $contact) {
            try {
                // Check if user already exists with this email
                $existingUser = User::where('email', $contact->email)->first();

                if ($existingUser) {
                    // Link existing user to contact
                    $contact->update(['user_id' => $existingUser->id]);

                    // Ensure user has collaborator role
                    if (!$existingUser->hasRole('collaborator')) {
                        $existingUser->assignRole('collaborator');
                    }

                    $this->newLine();
                    $this->info("✅ Linked existing user: {$contact->name} ({$contact->email})");
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'password' => Hash::make('bbounicornio123'),
                        'current_team_id' => $contact->team_id,
                        'phone' => $contact->phone,
                        'email_verified_at' => now(),
                    ]);

                    // Add user to team
                    $user->teams()->attach($contact->team_id);

                    // Assign collaborator role
                    $user->assignRole('collaborator');

                    // Link contact to user
                    $contact->update(['user_id' => $user->id]);

                    $this->newLine();
                    $this->info("✅ Created user: {$contact->name} ({$contact->email})");
                }

                $created++;

            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("❌ Error creating user for {$contact->name} ({$contact->email}): {$e->getMessage()}");
                Log::error("Error creating BBO user for contact {$contact->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Process completed!");
        $this->info("📊 Summary:");
        $this->info("   - Users created/linked: {$created}");
        $this->info("   - Errors: {$errors}");

        if ($totalContacts > $limit) {
            $remaining = $totalContacts - $limit;
            $this->warn("⚠️  {$remaining} contacts still need users. Run again to process more.");
        }

        return 0;
    }
}
