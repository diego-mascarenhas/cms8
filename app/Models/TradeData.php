<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeData extends Model
{
	protected $table = 'trade_data';

	protected $casts = [
		'data' => 'array',
	];

	protected $fillable = ['symbol', 'time_frame', 'open', 'close', 'high', 'low', 'premium', 'discount', 'tv5', 'tv15', 'tv240', 'volume', 'volatility', 'rsi', 'macd', 'adx', 'data'];
}