<?php

namespace App\Console\Commands;

use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class RescheduleMessageDeliveries extends Command
{
    protected $signature = 'messages:reschedule
                            {message_id : The ID of the message to reschedule}
                            {--delay=1 : Seconds delay between each delivery (default: 1)}
                            {--immediate : Send all immediately without delay}';

    protected $description = 'Reschedule pending deliveries to send them faster';

    public function handle()
    {
        $messageId = $this->argument('message_id');
        $delay = $this->option('immediate') ? 0 : (int) $this->option('delay');

        // Get pending deliveries scheduled for the future
        $pendingDeliveries = MessageDelivery::where('message_id', $messageId)
            ->where('status_id', 1) // pending
            ->whereNull('delivered_at')
            ->where(function ($query)
            {
                $query->whereNull('sent_at')
                    ->orWhere('sent_at', '>', now());
            })
            ->orderBy('sent_at', 'asc')
            ->get();

        if ($pendingDeliveries->isEmpty())
        {
            $this->error('No pending deliveries found for message ID: '.$messageId);

            return 1;
        }

        $this->info("Found {$pendingDeliveries->count()} pending deliveries");

        if (! $this->confirm('Do you want to reschedule these deliveries to send now?', true))
        {
            $this->info('Operation cancelled');

            return 0;
        }

        $bar = $this->output->createProgressBar($pendingDeliveries->count());
        $bar->start();

        $baseTime = now();
        $rescheduled = 0;

        foreach ($pendingDeliveries as $index => $delivery)
        {
            // Calculate new send time
            $newSentAt = $delay > 0
                ? $baseTime->copy()->addSeconds($index * $delay)
                : $baseTime;

            $delivery->update([
                'sent_at' => $newSentAt,
            ]);

            $rescheduled++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Successfully rescheduled {$rescheduled} deliveries");

        if ($delay > 0)
        {
            $this->info("   Delay between deliveries: {$delay} second(s)");
            $totalTime = $rescheduled * $delay;
            $this->info("   Total time to send all: ~{$totalTime} seconds");
        } else
        {
            $this->info('   All deliveries will be sent immediately');
        }

        return 0;
    }
}
