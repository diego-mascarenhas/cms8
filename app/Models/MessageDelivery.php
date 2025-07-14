<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDelivery extends Model
{
	use HasFactory;

	protected $fillable = [
		'team_id',
		'message_id',
		'contact_id',
		'smtp_id',
		'sent_at',
		'delivered_at',
		'removed_at',
		'status',
	];

	protected $dates = [
		'sent_at',
		'delivered_at',
		'removed_at',
	];

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function message()
	{
		return $this->belongsTo(Message::class);
	}

	public function contact()
	{
		return $this->belongsTo(Contact::class);
	}

	public function links()
	{
		return $this->hasMany(MessageDeliveryLink::class, 'message_delivery_id');
	}

    /**
     * Tracking events for this delivery
     */
    public function trackingEvents()
    {
        return $this->hasMany(MessageDeliveryTracking::class, 'message_delivery_id');
    }

    /**
     * Generate a tracking token for this delivery
     */
    public function getTrackingToken()
    {
        return hash('sha256', config('app.key') . $this->id);
    }

    /**
     * Get the tracking URL for this delivery (for opens)
     */
    public function getTrackingUrl()
    {
        return route('message.track', ['token' => $this->getTrackingToken()]);
    }

    /**
     * Get a tracked URL for click tracking
     */
    public function getTrackedUrl($originalUrl)
    {
        return route('message.track.click', ['token' => $this->getTrackingToken()]) . '?url=' . urlencode($originalUrl);
    }

    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->update([
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as opened
     */
    public function markAsOpened()
    {
        \Log::info('Intentando marcar como abierto', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        if (!$this->opened_at) {
            $this->update([
                'opened_at' => now(),
            ]);
            \Log::info('Marcado como abierto', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        } else {
            \Log::info('Ya estaba abierto', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        }
        $this->trackingEvents()->create([
            'event' => 'opened',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        \Log::info('Evento de tracking creado', ['id' => $this->id]);
    }

    /**
     * Status badge for UI
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->opened_at) {
            return '<span class="badge bg-info">Opened</span>';
        }
        if ($this->sent_at) {
            return '<span class="badge bg-success">Sent</span>';
        }
        return '<span class="badge bg-warning">Pending</span>';
    }
}
