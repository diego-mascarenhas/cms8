<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppFeedback extends Model
{
    use HasFactory;

    protected $table = 'app_feedback';

    protected $fillable = [
        'team_id',
        'user_id',
        'product',
        'answers',
        'comment',
        'message',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
