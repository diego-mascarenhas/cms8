<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'base_url', 'icon', 'color'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_sources')->withPivot('value');
    }

    public function getIconHtmlAttribute()
    {
        return sprintf(
            '<i class="fa fa-%s" style="color: %s;" title="%s"></i>',
            $this->icon,
            $this->color,
            $this->name
        );
    }
}