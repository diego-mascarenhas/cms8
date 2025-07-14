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
        foreach ($pendings as $delivery) {
            if (!$delivery->contact || !$delivery->contact->email) {
                $this->warn('Sin email para delivery ID: ' . $delivery->id);
                $errors++;
                continue;
            }
            try {
                Mail::to($delivery->contact->email)->send(new MessageDeliveryMail($delivery));
                $delivery->markAsSent();
                $this->info('Enviado a: ' . $delivery->contact->email);
                $sent++;
            } catch (\Exception $e) {
                $this->error('Error al enviar a ' . $delivery->contact->email . ': ' . $e->getMessage());
                $errors++;
            }
        }
        $this->info("Total enviados: $sent");
        $this->info("Total con error: $errors");
        return 0;
    }
}
