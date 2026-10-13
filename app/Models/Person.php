<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $fillable = [
        'name',
        'surname',
        'title',
        'role',
        'organization_name',
        'country',
    ];

    public function emails(): HasMany
    {
        return $this->hasMany(PersonEmail::class);
    }

    public function enrichments(): HasMany
    {
        return $this->hasMany(PersonEnrichment::class);
    }
}
