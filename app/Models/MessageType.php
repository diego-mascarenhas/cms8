<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageType extends Model
{
    use HasFactory;

    public $timestamps = false;

	protected $table = 'messages_type';

    protected $fillable = ['name', 'status'];

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
