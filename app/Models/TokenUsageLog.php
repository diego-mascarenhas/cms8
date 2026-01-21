<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'json_size',
        'toon_size',
        'json_tokens',
        'toon_tokens',
        'savings_percentage',
        'used_toon',
    ];

    protected $casts = [
        'used_toon' => 'boolean',
        'json_size' => 'integer',
        'toon_size' => 'integer',
        'json_tokens' => 'integer',
        'toon_tokens' => 'integer',
        'savings_percentage' => 'integer',
    ];

    /**
     * Get total API calls
     */
    public static function getTotalCalls(): int
    {
        return self::count();
    }

    /**
     * Get total tokens saved using Toon
     */
    public static function getTotalTokensSaved(): int
    {
        return self::where('used_toon', true)
            ->sum(\DB::raw('json_tokens - toon_tokens'));
    }

    /**
     * Get average savings percentage
     */
    public static function getAverageSavingsPercentage(): float
    {
        return round(
            self::where('used_toon', true)->avg('savings_percentage') ?? 0,
            2,
        );
    }

    /**
     * Get total tokens used (with Toon optimization)
     */
    public static function getTotalTokensUsed(): int
    {
        return self::where('used_toon', true)->sum('toon_tokens') +
               self::where('used_toon', false)->sum('json_tokens');
    }

    /**
     * Get total tokens that would have been used without Toon
     */
    public static function getTotalTokensWithoutToon(): int
    {
        return self::sum('json_tokens');
    }

    /**
     * Get calls count by service
     */
    public static function getCallsByService(): array
    {
        return self::select('service', \DB::raw('count(*) as count'))
            ->groupBy('service')
            ->pluck('count', 'service')
            ->toArray();
    }
}
