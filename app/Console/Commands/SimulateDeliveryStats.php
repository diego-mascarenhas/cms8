<?php

namespace App\Console\Commands;

use App\Models\MessageDeliveryStat;
use Illuminate\Console\Command;

class SimulateDeliveryStats extends Command
{
    protected $signature = 'message:simulate-stats {message_id} {--duration=60}';

    protected $description = 'Simulate real-time delivery stats changes for testing';

    public function handle()
    {
        $messageId = $this->argument('message_id');
        $duration = (int) $this->option('duration');

        $this->info("🚀 Simulating delivery stats for message {$messageId} for {$duration} seconds...");
        $this->info("📊 Watch the stats update in real-time at https://humano.test/message/{$messageId}");

        $startTime = time();

        while ((time() - $startTime) < $duration)
        {
            $stats = MessageDeliveryStat::where('message_id', $messageId)->first();

            if ($stats)
            {
                // Simulate gradual increases
                if (rand(1, 3) === 1) // 33% chance to increase
                {$newSent = min($stats->subscribers, $stats->sent + rand(0, 1));
                    $newDelivered = min($newSent, $stats->delivered + rand(0, 1));
                    $newOpened = min($newDelivered, $stats->opened + rand(0, 1));
                    $newClicks = min($newOpened, $stats->clicks + rand(0, 1));

                    $newRatio = $stats->subscribers > 0 ? round(($newOpened / $stats->subscribers) * 100, 2) : 0;

                    $stats->update([
                        'sent' => $newSent,
                        'delivered' => $newDelivered,
                        'opened' => $newOpened,
                        'clicks' => $newClicks,
                        'ratio' => $newRatio,
                    ]);

                    $this->line("📈 Updated: {$newSent} sent, {$newDelivered} delivered, {$newOpened} opened ({$newRatio}%)");
                }
            }

            sleep(3); // Wait 3 seconds between updates
        }

        $this->info('✅ Simulation completed!');

        return 0;
    }
}
