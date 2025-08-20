<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTracking extends Model
{
	use HasFactory;

	protected $table = 'notification_tracking';

	protected $fillable = [
		'notification_id',
		'event_type',
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
	 * Get the notification that this tracking belongs to
	 */
	public function notification()
	{
		return $this->belongsTo(Notification::class);
	}

	/**
	 * Scope for opened events
	 */
	public function scopeOpened($query)
	{
		return $query->where('event_type', 'opened');
	}

	/**
	 * Scope for clicked events
	 */
	public function scopeClicked($query)
	{
		return $query->where('event_type', 'clicked');
	}

	/**
	 * Create a tracking event
	 */
	public static function createEvent($notificationId, $eventType = 'opened', $metadata = [])
	{
		$request = request();

		return self::create([
			'notification_id' => $notificationId,
			'event_type' => $eventType,
			'tracked_at' => now(),
			'ip_address' => $request->ip(),
			'user_agent' => $request->userAgent(),
			'metadata' => $metadata,
		]);
	}
}
