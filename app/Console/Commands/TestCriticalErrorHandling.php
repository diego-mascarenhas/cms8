<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class TestCriticalErrorHandling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:critical-errors {message_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test critical error handling and automatic campaign pausing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $messageId = $this->argument('message_id');
        $message = Message::find($messageId);

        if (! $message)
        {
            $this->error("Message with ID {$messageId} not found");

            return 1;
        }

        $this->info("Testing critical error handling for message: {$message->name}");
        $this->info('Current status: '.($message->status_id ? 'Active' : 'Inactive'));

        // Test different types of critical errors
        $criticalErrors = [
            '550 5.7.0 domain is not configured with ORIGIN IP IN SPF',
            '535 5.7.8 Authentication credentials invalid',
            'Connection refused at SMTP server',
        ];

        // Create some dummy contact for testing if none exists
        if ($message->deliveries->count() < 3)
        {
            $this->info('Creating test deliveries...');
            $contact = Contact::first();

            if (! $contact)
            {
                $this->error('No contacts available for testing');

                return 1;
            }

            for ($i = 0; $i < 3; $i++)
            {
                MessageDelivery::create([
                    'team_id' => $message->team_id,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'status_id' => 1, // pending
                    'sent_at' => now()->addMinutes($i),
                ]);
            }
        }

        // Simulate critical errors
        $deliveries = MessageDelivery::where('message_id', $messageId)->take(3)->get();

        foreach ($deliveries as $index => $delivery)
        {
            $errorMessage = $criticalErrors[$index] ?? $criticalErrors[0];

            $this->info('Simulating error '.($index + 1).': '.substr($errorMessage, 0, 50).'...');

            // Mark as error with critical error message
            $delivery->markAsError($errorMessage);

            $this->info('  - Critical error detected: '.(Message::isCriticalError($errorMessage) ? 'YES' : 'NO'));
            $this->info('  - Recent errors count: '.$message->getRecentCriticalErrorsCount());
            $this->info('  - Should pause: '.($message->shouldPauseForErrors() ? 'YES' : 'NO'));
            $this->info('  - Campaign status: '.($message->fresh()->status_id ? 'Active' : 'Paused'));

            if ($message->fresh()->status_id == 0)
            {
                $this->warn('🚨 Campaign was automatically paused!');
                break;
            }

            $this->line('---');
        }

        // Final status
        $message = $message->fresh();
        $this->newLine();
        $this->info('Final Results:');
        $this->info('- Message Status: '.($message->status_id ? 'Active' : 'Paused'));
        $this->info('- Recent Critical Errors: '.$message->getRecentCriticalErrorsCount());
        $this->info('- Total Failed Deliveries: '.$message->deliveries()->where('status_id', 4)->count());

        if ($message->status_id == 0)
        {
            $this->warn('✅ Success! Campaign was automatically paused due to critical errors.');
        } else
        {
            $this->comment('Campaign is still active. May need more errors to trigger pause.');
        }

        return 0;
    }
}
