<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDownload extends Model
{
	use HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'user_id',
		'team_id',
		'invoice_id',
		'quarter',
		'year',
		'status',
		'file_path',
		'file_name',
		'error_message',
		'ip_address',
		'user_agent',
		'downloaded_at',
	];

	/**
	 * Get the user that owns the invoice download.
	 */
	public function user()
	{
		return $this->belongsTo(User::class);
	}

	/**
	 * Get the team that owns the invoice download.
	 */
	public function team()
	{
		return $this->belongsTo(Team::class);
	}
}
