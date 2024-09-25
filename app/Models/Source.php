<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'base_url', 'icon'];
}