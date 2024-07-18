<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasFactory;

    public $timestamps = false;

	protected $table = 'service_type';

    protected $fillable = ['name', 'desctiption', 'data', 'price', 'discount', 'frequency', 'status'];

    protected $casts = [
        'data' => 'array',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'type_id');
    }

    public static function types()
    {
        return self::all()->map(function ($data)
        {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }
}
