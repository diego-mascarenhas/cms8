<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class UpdateMessageDeliveryStats extends Command
{
    protected $signature = 'messages:update-delivery-stats';
    protected $description = 'Populate message_delivery_stats table for all messages';

    public function handle()
    {
        $this->info('Updating message_delivery_stats for all messages...');
        $messages = Message::all();
        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        foreach ($messages as $message) {
            $subscribers = Contact::whereHas('categories', function($q) use ($message) {
                $q->where('categories.id', $message->category_id);
            })->count();

            $deliveries = MessageDelivery::where('message_id', $message->id);
            $sent = (clone $deliveries)->whereNotNull('sent_at')->count();
            $delivered = (clone $deliveries)->whereNotNull('delivered_at')->count();
            $failed = (clone $deliveries)->where('status', 0)->count();
            $rejected = 0; // Adjust if you have logic for rejected
            $opened = 0; // If you have open tracking
            $unique_opens = 0; // If you have unique open tracking
            $unsubscribed = 0; // If you have unsubscribe tracking
            $clicks = 0; // If you have click tracking
            $remaining = $subscribers - $sent;
            $ratio = $sent > 0 ? round(($opened / $sent) * 100, 2) : 0;

            DB::table('message_delivery_stats')->updateOrInsert(
                ['message_id' => $message->id],
                [
                    'subscribers' => $subscribers,
                    'remaining' => $remaining,
                    'failed' => $failed,
                    'sent' => $sent,
                    'rejected' => $rejected,
                    'delivered' => $delivered,
                    'opened' => $opened,
                    'unique_opens' => $unique_opens,
                    'unsubscribed' => $unsubscribed,
                    'clicks' => $clicks,
                    'ratio' => $ratio,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $bar->advance();
        }
        $bar->finish();
        $this->info('\nDone.');
    }
}
