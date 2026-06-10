<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'invited_by_user_id',
        'invitee_name',
        'invitee_email',
        'plan_id',
        'plan_name',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
