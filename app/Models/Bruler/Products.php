<?php

namespace App\Models\Bruler;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
	protected $connection = 'mysql_tmp';

	protected $table = 'bruler_data';

	protected $fillable = ['type', 'bruler_id', 'data', 'status'];

	protected $casts = [
		'data' => 'array',
	];
}
