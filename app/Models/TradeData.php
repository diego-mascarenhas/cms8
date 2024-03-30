<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeData extends Model
{
	protected $table = 'trade_data';

	protected $casts = [
		'data' => 'array',
	];

	protected $fillable = ['symbol', 'open', 'close', 'high', 'low', 'tr5', 'tr15', 'tr240', 'volume', 'volatility', 'rsi', 'macd', 'adx', 'data', 'status'];
}