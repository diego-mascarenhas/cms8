<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageCampaignJob;
use App\Models\MessageDelivery;
use Illuminate\Console\Command;

class SendTrackedTestMail extends Command
{
    protected $signature = 'mail:send-tracked-test {delivery_id}';

    protected $description = 'Enviar un correo de prueba con tracking de apertura usando MessageDelivery.';

    public function handle()
    {
        $deliveryId = $this->argument('delivery_id');
        $delivery = MessageDelivery::with(['contact', 'message', 'team'])->find($deliveryId);

        if (! $delivery)
        {
            $this->error('No se encontró el MessageDelivery con ID: '.$deliveryId);

            return 1;
        }

        if (! $delivery->contact || ! $delivery->contact->email)
        {
            $this->error('El delivery no tiene contacto o email asociado.');

            return 1;
        }

        if (! $delivery->team)
        {
            $this->error('El delivery no tiene team asociado.');

            return 1;
        }

        // 🚀 Use the Job instead of sending directly
        SendMessageCampaignJob::dispatch($delivery);

        $this->info('Job de envío despachado para: '.$delivery->contact->email.' (Team: '.$delivery->team->name.')');
        $this->info('El correo se enviará usando la configuración del equipo y el proveedor configurado.');

        return 0;
    }
}
