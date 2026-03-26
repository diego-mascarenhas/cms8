<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamFileHistory extends Model
{
    protected $fillable = [
        'team_file_id',
        'team_id',
        'user_id',
        'action',
        'file_name',
        'archived_media_id',
    ];

    public function teamFile(): BelongsTo
    {
        return $this->belongsTo(TeamFile::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
