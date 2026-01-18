<?php

namespace App\Observers;

use App\Models\StripeSubscription;
use Illuminate\Support\Facades\Log;

class StripeSubscriptionObserver
{
    /**
     * Handle the StripeSubscription "updated" event.
     */
    public function updated(StripeSubscription $subscription): void
    {
        // Solo procesar si:
        // 1. Cambió el status
        // 2. Tiene auto_suspend activado en metadata
        // 3. NO es una compra manual (detectada por stripe_id)
        if (
            $subscription->isDirty('status') &&
            ! str_starts_with($subscription->stripe_id, 'manual-') &&
            data_get($subscription->data, 'auto_suspend')
        ) {
            Log::info('Subscription status changed, queuing sync', [
                'subscription_id' => $subscription->id,
                'stripe_id' => $subscription->stripe_id,
                'old_status' => $subscription->getOriginal('status'),
                'new_status' => $subscription->status,
            ]);

            // Aquí puedes agregar lógica adicional si es necesario
            // Por ejemplo, sincronizar con WHM o enviar notificaciones
        }
    }
}
