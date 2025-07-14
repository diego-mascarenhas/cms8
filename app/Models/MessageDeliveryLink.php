<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDeliveryLink extends Model
{
	use HasFactory;

	public $timestamps = false;

	protected $fillable = [
		'message_delivery_id',
		'created_at',
		'link',
	];

	protected $dates = [
		'created_at',
	];

	public function messageDelivery()
	{
		return $this->belongsTo(MessageDelivery::class, 'message_delivery_id');
	}
}
