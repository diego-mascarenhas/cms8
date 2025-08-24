<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
	use HasFactory;
	use SoftDeletes;

	public $timestamps = true;

	protected $table = 'messages';

	protected $fillable = ['name', 'type_id', 'category_id', 'contact_status_id', 'template_id', 'text', 'status_id', 'show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking', 'min_hours_between_emails', 'team_id', 'started_at'];

	protected $casts = [
		'status_id' => 'boolean',
		'show_unsubscribe' => 'boolean',
		'enable_open_tracking' => 'boolean',
		'enable_click_tracking' => 'boolean',
		'min_hours_between_emails' => 'integer',
		'started_at' => 'datetime',
	];

	protected static function booted()
	{
		static::addGlobalScope('team', function (Builder $builder)
		{
			if (auth()->check())
			{
				$builder->where('team_id', auth()->user()->currentTeam->id);
			}
		});

		static::creating(function ($model)
		{
			if (! $model->team_id && auth()->check())
			{
				$model->team_id = auth()->user()->currentTeam->id;
			}
		});
	}

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function type()
	{
		return $this->belongsTo(MessageType::class);
	}

	public function category()
	{
		return $this->belongsTo(Category::class);
	}

	public function template()
	{
		return $this->belongsTo(Template::class);
	}

	public function deliveries()
	{
		return $this->hasMany(MessageDelivery::class);
	}

	public function contactStatus()
	{
		return $this->belongsTo(ContactStatus::class);
	}

	/**
	 * Check if this message can be sent to a specific contact based on the minimum hours between emails
	 */
	public function canSendToContact(Contact $contact): bool
	{
		// If min_hours_between_emails is 0, always allow sending
		if ($this->min_hours_between_emails <= 0) {
			return true;
		}

		// Get the last email sent to this contact from any message in the same team
		$lastDelivery = MessageDelivery::where('contact_id', $contact->id)
			->where('team_id', $this->team_id)
			->whereNotNull('sent_at')
			->orderBy('sent_at', 'desc')
			->first();

		// If no previous email was sent, allow sending
		if (!$lastDelivery) {
			return true;
		}

		// Calculate hours since last email
		$hoursSinceLastEmail = now()->diffInHours($lastDelivery->sent_at);

		// Check if enough time has passed
		return $hoursSinceLastEmail >= $this->min_hours_between_emails;
	}

	/**
	 * Get the next available time to send an email to a specific contact
	 */
	public function getNextAvailableTimeForContact(Contact $contact): ?\Carbon\Carbon
	{
		// If min_hours_between_emails is 0, can send immediately
		if ($this->min_hours_between_emails <= 0) {
			return now();
		}

		// Get the last email sent to this contact
		$lastDelivery = MessageDelivery::where('contact_id', $contact->id)
			->where('team_id', $this->team_id)
			->whereNotNull('sent_at')
			->orderBy('sent_at', 'desc')
			->first();

		// If no previous email was sent, can send immediately
		if (!$lastDelivery) {
			return now();
		}

		// Calculate next available time
		return $lastDelivery->sent_at->addHours($this->min_hours_between_emails);
	}
}
