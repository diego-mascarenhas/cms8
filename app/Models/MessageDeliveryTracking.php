<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryTracking extends Model
{
	use HasFactory;

	protected $table = 'message_delivery_tracking';

	protected $fillable = [
		'message_delivery_id',
		'event',
		'tracked_at',
		'ip_address',
		'user_agent',
		'country',
		'city',
		'metadata',
	];

	protected $casts = [
		'tracked_at' => 'datetime',
		'metadata' => 'array',
	];

	/**
	 * Get the message delivery that this tracking belongs to
	 */
	public function delivery()
	{
		return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
	}

	/**
	 * Scope for opened events
	 */
	public function scopeOpened($query)
	{
		return $query->where('event', 'opened');
	}

	/**
	 * Scope for clicked events
	 */
	public function scopeClicked($query)
	{
		return $query->where('event', 'clicked');
	}

	/**
	 * Create a tracking event
	 */
	public static function createEvent($messageDeliveryId, $eventType = 'opened', $metadata = [])
	{
		$request = request();

		return self::create([
			'message_delivery_id' => $messageDeliveryId,
			'event' => $eventType,
			'tracked_at' => now(),
			'ip_address' => $request->ip(),
			'user_agent' => $request->userAgent(),
			'metadata' => $metadata,
		]);
	}
}
