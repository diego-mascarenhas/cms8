<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSlipAction extends Model
{
	use HasFactory;

	public $timestamps = false;

	protected $table = 'order_slips_actions';

	protected $fillable = ['name', 'url'];

	public function commands()
	{
		return $this->hasMany(OrderSlipCommand::class);
	}
}