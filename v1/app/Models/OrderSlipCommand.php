<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderSlipCommand extends Model
{
	use HasFactory;

	public $timestamps = false;

	protected $table = 'order_slips_commands';

	protected $fillable = ['name', 'trigger', 'action_id'];

	public function action()
	{
		return $this->belongsTo(OrderSlipAction::class, 'action_id');
	}
}