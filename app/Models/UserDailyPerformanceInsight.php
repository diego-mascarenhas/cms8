<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyPerformanceInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'insight_date',
        'performance_ratio',
        'headline',
        'focus',
        'message',
        'context_snapshot',
    ];

    protected $casts = [
        'insight_date' => 'date',
        'performance_ratio' => 'decimal:2',
        'context_snapshot' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Headlines from the insight pipeline may end with one emoji cluster (no space). Split so the word stays in the title row and the emoji can sit beside the sparkles icon.
     *
     * @return array{text: string, emoji: string}
     */
    public static function splitHeadlineWordAndTrailingEmoji(?string $headline): array
    {
        $headline = trim((string) $headline);
        if ($headline === '')
        {
            return ['text' => '', 'emoji' => ''];
        }

        if (preg_match('/^(.+?)(\p{Extended_Pictographic}(?:\x{FE0F}|\x{200D}\p{Extended_Pictographic})*)$/us', $headline, $matches) === 1)
        {
            return [
                'text' => $matches[1],
                'emoji' => $matches[2],
            ];
        }

        return ['text' => $headline, 'emoji' => ''];
    }
}
