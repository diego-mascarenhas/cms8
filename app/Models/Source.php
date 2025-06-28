<?php

namespace App\Models;

use App\Traits\HasSourceIcons;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasSourceIcons;

    public $timestamps = false;

    protected $fillable = ['name', 'base_url', 'icon', 'color'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_sources')->withPivot('value');
    }

    public static function getOptions()
    {
        return self::all()->map(function ($data) {
            return [
                'id' => $data->id,
                'name' => $data->name,
            ];
        });
    }
}
