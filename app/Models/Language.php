<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $primaryKey = 'code';

    public $timestamps = false;

    public $incrementing = false;
    
    protected $keyType = 'string';

    protected $fillable = ['code', 'name'];
}
