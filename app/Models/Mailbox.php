<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mailbox extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'protocol',
        'folder',
    ];

    protected $casts = [
        'port' => 'integer',
        'password' => 'encrypted',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
