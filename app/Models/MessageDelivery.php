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
}
