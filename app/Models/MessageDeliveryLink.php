<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryLink extends Model
{
	use HasFactory;

	public $timestamps = true;

	protected $fillable = [
		'message_delivery_id',
		'link',
	];

	protected $casts = [
		'created_at' => 'datetime',
	];

	public function messageDelivery()
	{
		return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
	}
}
