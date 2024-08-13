<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dotlogics\Grapesjs\App\Traits\EditableTrait;
use Dotlogics\Grapesjs\App\Contracts\Editable;

class Template extends Model implements Editable
{
    use EditableTrait;
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'templates';

    protected $fillable = ['name', 'gjs_data', 'status'];

    protected $casts = [
        'gjs_data' => 'array',
    ];

    public static function getOptions()
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