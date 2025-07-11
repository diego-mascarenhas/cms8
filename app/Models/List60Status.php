<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class List60Status extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'list60_statuses';

    protected $fillable = [
        'name',
        'label_class',
    ];
}
