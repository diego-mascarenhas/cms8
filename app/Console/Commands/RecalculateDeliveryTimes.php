<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class RecalculateDeliveryTimes extends Command
{
    protected $signature = 'emails:recalculate-times {--message-id= : Recalculate only deliveries for specific message ID} {--dry-run : Show what would be recalculated without actually updating} {--limit=1000 : Maximum number of deliveries to recalculate}';

    protected $description = 'Recalculate delivery times for pending deliveries using current configuration. Perfect after changing delay settings.';

    public function handle()
    {
        $messageId = $this->option('message-id');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($messageId)
        {
            $message = Message::find($messageId);
            if (! $message)
            {
                $this->error("❌ Message ID {$messageId} not found.");

                return 1;
            }
            $this->info("🔄 Recalculating delivery times for Message ID: {$messageId}...");
            $this->comment("   📝 Campaign: {$message->name}");
        } else
        {
            $this->info('🔄 Recalculating delivery times for all pending deliveries...');
        }

        // Get deliveries that are scheduled for the future (need recalculation)
        $query = MessageDelivery::where('status_id', 1) // pending
            ->where('sent_at', '>', now()) // scheduled for future
            ->with(['contact', 'message']);

        if ($messageId)
        {
            $query->where('message_id', $messageId);
        }

        $deliveries = $query->orderBy('message_id')
            ->orderBy('sent_at')
            ->limit($limit)
            ->get();

        if ($deliveries->isEmpty())
        {
            if ($messageId)
            {
                $this->info("📭 No deliveries scheduled for future found for Message ID: {$messageId}.");
            } else
            {
                $this->info('📭 No deliveries scheduled for future found.');
            }

            return 0;
        }

        $this->info("📧 Found {$deliveries->count()} deliveries scheduled for future");

        // Show current configuration
        $baseMinutes = config('services.email.delay.base_minutes', 1);
        $maxRandomSeconds = config('services.email.delay.random_seconds', 60);
        $this->comment("⚙️  Current configuration: {$baseMinutes} minutes base + 0-{$maxRandomSeconds} seconds random");

        if ($dryRun)
        {
            $this->info('🔍 DRY RUN MODE - Showing what would be recalculated:');

            // Group by message for better display
            $groupedDeliveries = $deliveries->groupBy('message_id');

            foreach ($groupedDeliveries as $msgId => $msgDeliveries)
            {
                $message = $msgDeliveries->first()->message;
                $this->newLine();
                $this->info("📝 Message {$msgId}: {$message->name}");
                $this->comment("   Deliveries to recalculate: {$msgDeliveries->count()}");

                // Show first few and last few scheduled times
                $first5 = $msgDeliveries->take(3);
                $this->comment('   Current scheduled times (first 3):');
                foreach ($first5 as $delivery)
                {
                    $this->comment("     ID {$delivery->id}: {$delivery->sent_at} → {$delivery->contact->email}");
                }

                if ($msgDeliveries->count() > 3)
                {
                    $this->comment('     ... and '.($msgDeliveries->count() - 3).' more');
                }

                // Calculate what the new times would be
                $this->comment('   Would be recalculated to start from: '.now()->format('Y-m-d H:i:s'));
            }

            return 0;
        }

        // Confirm before recalculating
        $confirmMessage = $messageId
        	? "⚠️  Are you sure you want to recalculate {$deliveries->count()} delivery times for Message ID {$messageId}?"
        	: "⚠️  Are you sure you want to recalculate {$deliveries->count()} delivery times?";

        if (! $this->confirm($confirmMessage))
        {
            $this->info('❌ Operation cancelled.');

            return 1;
        }

        $this->info('🚀 Starting recalculation...');

        $updated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($deliveries->count());
        $bar->start();

        // Group deliveries by message for sequential scheduling
        $groupedDeliveries = $deliveries->groupBy('message_id');

        foreach ($groupedDeliveries as $msgId => $msgDeliveries)
        {
            $baseTime = now(); // Start from current time
            $deliveryIndex = 0;

            foreach ($msgDeliveries as $delivery)
            {
                try
                {
                    // Calculate new scheduled time using current configuration
                    $delayMinutes = $deliveryIndex * $baseMinutes;
                    $randomSecondsToAdd = rand(0, $maxRandomSeconds);
                    $newScheduledTime = $baseTime->copy()->addMinutes($delayMinutes)->addSeconds($randomSecondsToAdd);

                    // Update the delivery
                    $delivery->update([
                        'sent_at' => $newScheduledTime,
                    ]);

                    $updated++;
                    $deliveryIndex++;
                } catch (\Exception $e)
                {
                    $this->newLine();
                    $this->error("❌ Error updating delivery {$delivery->id}: {$e->getMessage()}");
                    $errors++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Recalculation Summary:');
        $this->info("🔄 Deliveries recalculated: {$updated}");
        $this->info("❌ Errors: {$errors}");

        if ($updated > 0)
        {
            $this->info('⏰ New schedule starts from: '.now()->format('Y-m-d H:i:s'));
            $this->info('🚀 Deliveries will now be sent using the optimized configuration!');
            $this->comment('   Next check with: php artisan campaigns:send-scheduled');
        }

        return 0;
    }
}
