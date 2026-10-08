<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessActiveCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:process-active
                            {--message= : Process only this message ID}';

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

        $messageId = $this->option('message');

        $query = Message::query()
            ->where('status_id', 1)
            ->whereNotNull('started_at');

        if ($messageId !== null && $messageId !== '')
        {
            $query->whereKey((int) $messageId);
        }

        $activeMessages = $query->get();

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

        Log::info('ProcessActiveCampaigns completed', [
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
            ->whereNull('campaign_id')
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
        $lastSentAt = MessageDelivery::where('message_id', $message->id)
            ->whereNull('campaign_id')
            ->whereNotNull('sent_at')
            ->max('sent_at');

        $baseTime = $lastSentAt ? Carbon::parse($lastSentAt) : $message->started_at;
        $createdCount = 0;
        $deliveryIndex = MessageDelivery::where('message_id', $message->id)
            ->whereNull('campaign_id')
            ->count();
        $message->loadMissing('team');
        $team = $message->team;
        $fast = (bool) $team?->sendsMailerWithoutSpacing();
        $spacingSeconds = $team?->mailerSendSpacingSeconds() ?? (86400 / 3000);
        $jitterSeconds = $team?->mailerSendJitterSeconds() ?? 0;
        $coldSpacing = $team?->mailerSpacingSecondsForContact(false) ?? $spacingSeconds;
        $reached = $fast
            ? $message->previouslyReachedContactIds($validContactIds)
            : [];
        if ($fast)
        {
            $validContacts = $validContacts
                ->sortBy(fn ($contact): string => sprintf(
                    '%d-%08d',
                    isset($reached[$contact->id]) ? 0 : 1,
                    $contact->id,
                ))
                ->values();
        }
        $coldIndex = $fast
            ? MessageDelivery::query()
                ->where('message_id', $message->id)
                ->whereNull('campaign_id')
                ->when($reached !== [], fn ($query) => $query->whereNotIn('contact_id', array_keys($reached)))
                ->count()
            : 0;

        foreach ($validContacts as $contact)
        {
            // Check if delivery already exists
            $existingDelivery = MessageDelivery::where('message_id', $message->id)
                ->whereNull('campaign_id')
                ->where('contact_id', $contact->id)
                ->first();

            if (! $existingDelivery)
            {
                $previouslyReached = isset($reached[$contact->id]);
                if ($fast && $previouslyReached)
                {
                    $extraSeconds = 0;
                } elseif ($fast)
                {
                    $extraSeconds = (int) round(($coldIndex + 1) * $coldSpacing);
                    $coldIndex++;
                } else
                {
                    $extraSeconds = (int) round(($deliveryIndex * $spacingSeconds) + ($jitterSeconds > 0 ? rand(0, $jitterSeconds) : 0));
                }
                $scheduledTime = $baseTime->copy()->addSeconds($extraSeconds);

                // Ensure scheduled time respects minimum hours between emails
                $nextAvailableTime = $message->getNextAvailableTimeForContact($contact);
                if ($scheduledTime->lt($nextAvailableTime))
                {
                    $scheduledTime = $nextAvailableTime->copy()->addSeconds($extraSeconds);
                }

                $scheduledTime = $message->alignScheduledTimeWithSendingSchedule($scheduledTime);

                MessageDelivery::create([
                    'team_id' => $message->team_id,
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'status_id' => 1, // pending
                    'scheduled_for' => $scheduledTime, // When to send
                ]);

                $createdCount++;
                $deliveryIndex++;

                Log::info('New delivery created dynamically', [
                    'message_id' => $message->id,
                    'contact_id' => $contact->id,
                    'contact_email' => $contact->email,
                    'scheduled_at' => $scheduledTime,
                ]);
            }
        }

        return $createdCount;
    }

    /**
     * Get contacts for a message based on its category
     */
    private function getContactsForMessage(Message $message)
    {
        return $message->audienceContactsQuery()->get();
    }
}
