<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessActiveCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process active campaigns and create message deliveries with scheduled times';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Processing active campaigns...');

        // Get all active messages that have been started
        $activeMessages = Message::where('status_id', 1)
            ->whereNotNull('started_at')
            ->get();

        if ($activeMessages->isEmpty())
        {
            $this->info('📭 No active campaigns found.');

            return 0;
        }

        $totalProcessed = 0;
        $totalCreated = 0;

        foreach ($activeMessages as $message)
        {
            $this->info("📧 Processing campaign: {$message->name} (ID: {$message->id})");

            $created = $this->processMessageCampaign($message);
            $totalCreated += $created;
            $totalProcessed++;

            if ($created > 0)
            {
                $this->info("   ✅ Created {$created} new deliveries");
            } else
            {
                $this->info('   ⏸️  No new deliveries needed');
            }
        }

        $this->info("🎉 Processed {$totalProcessed} campaigns, created {$totalCreated} deliveries");

        Log::info('📊 ProcessActiveCampaigns completed', [
            'campaigns_processed' => $totalProcessed,
            'deliveries_created' => $totalCreated,
        ]);

        return 0;
    }

    /**
     * Process a single message campaign (Dynamic: adds new, removes invalid)
     */
    private function processMessageCampaign(Message $message): int
    {
        // Get contacts that SHOULD receive this message based on current criteria
        $validContacts = $this->getContactsForMessage($message);
        $validContactIds = $validContacts->pluck('id')->toArray();

        // Step 1: Remove pending deliveries for contacts that NO LONGER meet criteria
        $removedCount = MessageDelivery::where('message_id', $message->id)
            ->whereNull('sent_at') // Only remove pending deliveries, not sent ones
            ->whereNotIn('contact_id', $validContactIds)
            ->delete();

        if ($removedCount > 0)
        {
            $this->info("   🗑️  Removed {$removedCount} deliveries for contacts that no longer meet criteria");
            Log::info('Removed invalid pending deliveries', [
                'message_id' => $message->id,
                'removed_count' => $removedCount,
            ]);
        }

        // Step 2: Create deliveries for contacts that meet criteria and don't have one yet
        $lastDelivery = MessageDelivery::where('message_id', $message->id)
            ->orderBy('sent_at', 'desc')
            ->first();

        $baseTime = $lastDelivery ? $lastDelivery->sent_at : $message->started_at;
        $createdCount = 0;
        $deliveryIndex = MessageDelivery::where('message_id', $message->id)->count();

        foreach ($validContacts as $contact)
        {
            // Check if delivery already exists
            $existingDelivery = MessageDelivery::where('message_id', $message->id)
                ->where('contact_id', $contact->id)
                ->first();

            if (! $existingDelivery)
            {
                // Check if we can send to this contact based on minimum hours between emails
                if (! $message->canSendToContact($contact))
                {
                    $nextAvailableTime = $message->getNextAvailableTimeForContact($contact);
                    $this->info("   ⏰ Skipping {$contact->email} - next available: {$nextAvailableTime->format('Y-m-d H:i:s')}");

                    continue;
                }

                // Calculate scheduled time based on the last delivery + random interval
                $baseMinutes = config('services.email.delay.base_minutes', 1);
                $maxRandomSeconds = config('services.email.delay.random_seconds', 60);

                $delayMinutes = $deliveryIndex * $baseMinutes;
                $randomSeconds = rand(0, $maxRandomSeconds);
                $scheduledTime = $baseTime->copy()->addMinutes($delayMinutes)->addSeconds($randomSeconds);

                // Ensure scheduled time respects minimum hours between emails
                $nextAvailableTime = $message->getNextAvailableTimeForContact($contact);
                if ($scheduledTime->lt($nextAvailableTime))
                {
                    $scheduledTime = $nextAvailableTime->copy()->addMinutes($delayMinutes)->addSeconds($randomSeconds);
                }

                MessageDelivery::create([
                    'team_id' => $message->team_id,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'status_id' => 1, // pending
                    'scheduled_for' => $scheduledTime, // When to send
                ]);

                $createdCount++;
                $deliveryIndex++;

                Log::info('📧 New delivery created dynamically', [
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'contact_email' => $contact->email,
                    'scheduled_at' => $scheduledTime,
                ]);

                // Create multiple deliveries per run but limit to avoid overload
                $maxDeliveries = config('services.email.processing.deliveries_per_campaign_run', 30); // Max 30 per campaign per run
                if ($createdCount >= $maxDeliveries)
                {
                    break;
                }
            }
        }

        return $createdCount;
    }

    /**
     * Get contacts for a message based on its category
     */
    private function getContactsForMessage(Message $message)
    {
        $query = null;

        if ($message->category)
        {
            // If category is specified, get contacts from that category
            $query = $message->category->contacts();

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        } else
        {
            // If no category (NULL), get all contacts from the team (entire database)
            $query = \App\Models\Contact::where('team_id', $message->team_id)
                ->whereNotNull('email');

            // Filter by contact status - use message's contact_status_id or default to active (1)
            $statusId = $message->contact_status_id ?: 1;
            $query->where('status_id', $statusId);
        }

        // Exclude test/demo email addresses
        $testDomains = [
            '@example.org',
            '@example.net',
            '@example.com',
            '@demo.com',
            '@test.com',
            '@localhost',
            '@testing.com',
            '@dummy.com',
            '@fake.com',
        ];

        foreach ($testDomains as $domain)
        {
            $query->where('email', 'not like', '%'.$domain);
        }

        return $query->get();
    }
}
