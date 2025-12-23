<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageCampaignJob;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class SendPendingMessages extends Command
{
    protected $signature = 'messages:send-pending';

    protected $description = 'Enviar todos los MessageDelivery pendientes (sent_at null) usando la plantilla asociada.';

    public function handle()
    {
        $pendings = MessageDelivery::whereNull('sent_at')->with(['contact', 'message.template', 'team'])->get();
        $sent = 0;
        $errors = 0;
        $delay = 0;

        foreach ($pendings as $delivery)
        {
            if (! $delivery->contact || ! $delivery->contact->email)
            {
                $this->warn('No email for delivery ID: '.$delivery->id);
                $errors++;

                continue;
            }

            // Check that the message is active
            if (! $delivery->message || $delivery->message->status_id != 1)
            {
                $this->warn('Inactive message for delivery ID: '.$delivery->id);
                $errors++;

                continue;
            }

            // Check that team exists
            if (! $delivery->team)
            {
                $this->warn('No team for delivery ID: '.$delivery->id);
                $errors++;

                continue;
            }

            try
            {
                // 🚀 Use the Job instead of Mailable directly
                // Random delay pequeño (3-15 segundos) para evitar spam flags
                $randomDelay = rand(3, 15);

                SendMessageCampaignJob::dispatch($delivery)
                    ->onQueue('mailer')
                    ->delay(now()->addSeconds($randomDelay));

                $this->info('Queued job for: '.$delivery->contact->email.' (delay: '.$randomDelay.'s, team: '.$delivery->team->name.')');
                $sent++;
            } catch (\Exception $e)
            {
                $this->error('Error queueing job for '.$delivery->contact->email.': '.$e->getMessage());
                $delivery->markAsError();
                $errors++;
            }
        }

        $this->info("Total jobs queued: $sent");
        $this->info("Total errors: $errors");

        return 0;
    }
}
