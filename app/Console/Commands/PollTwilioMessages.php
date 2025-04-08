<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class PollTwilioMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:poll {--minutes=15}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll Twilio for new messages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        $this->info("Polling for messages in the last {$minutes} minutes...");

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $client = new Client($sid, $token);

        // Get messages sent to your Twilio number
        $messages = $client->messages->read([
            'dateSentAfter' => now()->subMinutes($minutes)->format('c')
        ]);

        $newCount = 0;

        foreach ($messages as $message) {
            // Determine if it's WhatsApp
            $isWhatsApp = false;
            if (strpos($message->from, 'whatsapp:') !== false || strpos($message->to, 'whatsapp:') !== false) {
                $isWhatsApp = true;
            }

            $channel = $isWhatsApp ? 'whatsapp' : 'sms';
            
            // Check if we already have this message
            if (!Conversation::where('message_sid', $message->sid)->exists()) {
                // Determine direction
                $direction = 'inbound';
                if (strpos($message->from, config('services.twilio.from')) !== false ||
                    strpos($message->from, '+14155238886') !== false) {
                    $direction = 'outbound';
                }

                Conversation::create([
                    'message_sid' => $message->sid,
                    'channel' => $channel,
                    'from' => $message->from,
                    'to' => $message->to,
                    'body' => $message->body,
                    'status' => $direction === 'inbound' ? 'received' : 'sent',
                    'direction' => $direction,
                    'metadata' => [
                        'twilio_data' => [
                            'sid' => $message->sid,
                            'status' => $message->status,
                            'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                        ]
                    ]
                ]);

                $newCount++;
                $this->info("New {$channel} message from {$message->from}: {$message->body}");
            }
        }

        if ($newCount === 0) {
            $this->info("No new messages found.");
        } else {
            $this->info("Found {$newCount} new messages.");
        }

        return 0;
    }
}
