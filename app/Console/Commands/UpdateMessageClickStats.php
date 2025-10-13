<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateMessageClickStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:update-click-stats {--message-id= : Update stats for specific message ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update message delivery statistics including clicks and opens for SMTP tracking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Updating message click and open statistics...');

        $messageId = $this->option('message-id');

        if ($messageId)
        {
            $messages = Message::where('id', $messageId)->get();
            if ($messages->isEmpty())
            {
                $this->error("Message with ID {$messageId} not found.");

                return 1;
            }
        } else
        {
            // Update stats for all messages that have deliveries
            $messages = Message::whereHas('deliveries')->get();
        }

        $updatedCount = 0;

        foreach ($messages as $message)
        {
            $this->updateMessageStats($message);
            $updatedCount++;
        }

        $this->info("✅ Updated statistics for {$updatedCount} messages");

        Log::info('📊 Message click stats updated', [
            'messages_updated' => $updatedCount,
            'specific_message' => $messageId,
        ]);

        return 0;
    }

    /**
     * Update statistics for a specific message
     */
    private function updateMessageStats(Message $message): void
    {
        $deliveries = MessageDelivery::where('message_id', $message->id);

        // Calculate statistics
        $stats = [
            'sent' => $deliveries->whereNotNull('sent_at')->count(),
            'delivered' => $deliveries->whereNotNull('delivered_at')->count(),
            'opened' => $deliveries->whereNotNull('opened_at')->count(),
            'clicks' => $deliveries->whereNotNull('clicked_at')->count(),
            'failed' => $deliveries->whereIn('status_id', [4, 5, 6])->count(), // error, bounced, complained
        ];

        // Get or create stats record
        $messageStats = MessageDeliveryStat::firstOrCreate(
            ['message_id' => $message->id],
            [
                'subscribers' => 0,
                'remaining' => 0,
                'failed' => 0,
                'sent' => 0,
                'rejected' => 0,
                'delivered' => 0,
                'opened' => 0,
                'unsubscribed' => 0,
                'clicks' => 0,
                'unique_opens' => 0,
                'ratio' => 0,
            ],
        );

        // Update with calculated stats
        $messageStats->update([
            'sent' => $stats['sent'],
            'delivered' => $stats['delivered'],
            'opened' => $stats['opened'],
            'clicks' => $stats['clicks'],
            'failed' => $stats['failed'],
            'ratio' => $stats['sent'] > 0 ? round(($stats['opened'] / $stats['sent']) * 100, 2) : 0,
        ]);

        $this->line("   📧 Message '{$message->name}': {$stats['sent']} sent, {$stats['delivered']} delivered, {$stats['opened']} opened, {$stats['clicks']} clicked");
    }
}
