<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'notification_type',
        'status',
        'scheduled_at',
        'sent_at',
        'recipient_email',
        'recipient_name',
        'body',
        'opened_at',
        'open_count',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'open_count' => 'integer',
        'metadata' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(StripeSubscription::class, 'subscription_id');
    }

    /**
     * Get notification type label
     */
    public function getTypeLabel(): string
    {
        return match ($this->notification_type)
        {
            'warning_5_days' => 'Aviso 5 días antes',
            'warning_2_days' => 'Aviso 2 días antes',
            'suspended' => 'Servicio suspendido',
            'reactivated' => 'Servicio reactivado',
            default => $this->notification_type,
        };
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status)
        {
            'pending' => 'Pendiente',
            'sent' => 'Enviado',
            'failed' => 'Fallido',
            default => $this->status,
        };
    }

    /**
     * Generate a tracking token for this notification
     */
    public function getTrackingToken(): string
    {
        return hash('sha256', config('app.key').$this->id);
    }

    /**
     * Get the tracking URL for open events
     */
    public function getTrackingUrl(): string
    {
        return route('notification.track.pixel', ['token' => $this->getTrackingToken()]);
    }

    /**
     * Mark as sent and add tracking pixel to body
     */
    public function markAsSent(?string $body = null): void
    {
        $data = [
            'status' => 'sent',
            'sent_at' => now(),
        ];

        if ($body !== null)
        {
            // Agregar tracking pixel antes del cierre de </body>
            $trackingPixel = '<img src="'.$this->getTrackingUrl().'" width="1" height="1" border="0" style="display: block; width: 1px; height: 1px;" alt="" />';
            $bodyWithTracking = str_replace('</body>', $trackingPixel.'</body>', $body);

            $data['body'] = $bodyWithTracking;
        }

        $this->update($data);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }
}
