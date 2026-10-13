<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonEmail extends Model
{
    protected $fillable = [
        'person_id',
        'email',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
