<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MessageDelivery;
use App\Mail\MessageDeliveryMail;
use Illuminate\Support\Facades\Mail;

class SendPendingMessages extends Command
{
    protected $signature = 'messages:send-pending';
    protected $description = 'Enviar todos los MessageDelivery pendientes (sent_at null) usando la plantilla asociada.';

    public function handle()
    {
        $pendings = MessageDelivery::whereNull('sent_at')->with(['contact', 'message.template'])->get();
        $sent = 0;
        $errors = 0;
        $delay = 0;
        foreach ($pendings as $delivery) {
            if (!$delivery->contact || !$delivery->contact->email) {
                $this->warn('No email for delivery ID: ' . $delivery->id);
                $errors++;
                continue;
            }
            // Check that the message is active
            if (!$delivery->message || $delivery->message->status_id != 1) {
                $this->warn('Inactive message for delivery ID: ' . $delivery->id);
                $errors++;
                continue;
            }
            try {
                $randomDelay = rand(60, 300); // 60s to 300s (1-5 min)
                Mail::to($delivery->contact->email)
                    ->queue(
                        (new MessageDeliveryMail($delivery))
                            ->onQueue('mailer')
                            ->delay(now()->addSeconds($delay))
                    );
                $this->info('Queued to: ' . $delivery->contact->email . ' (delay: ' . $delay . 's)');
                $sent++;
                $delay += $randomDelay;
            } catch (\Exception $e) {
                $this->error('Error queueing to ' . $delivery->contact->email . ': ' . $e->getMessage());
                $delivery->markAsError();
                $errors++;
            }
        }
        $this->info("Total queued: $sent");
        $this->info("Total errors: $errors");
        return 0;
    }
}
