<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactStatus extends Model
{
    public $timestamps = false;

    public static function getOptions()
    {
        return self::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
