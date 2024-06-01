<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSlip extends Model
{
	use HasFactory;

	protected $table = 'order_slips';

	protected $fillable = ['type', 'data'];

	protected $casts = [
		'data' => 'array',
	];
}
