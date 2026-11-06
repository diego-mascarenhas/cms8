<?php

namespace App\Models;

use App\Enums\PaidAdUsageKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaidAdUsageLog extends Model
{
    /** @use HasFactory<\Database\Factories\PaidAdUsageLogFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'kind',
        'quantity',
    ];

    protected $casts = [
        'kind' => PaidAdUsageKind::class,
        'quantity' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public static function record(int $teamId, PaidAdUsageKind $kind, int $quantity): void
    {
        if ($quantity < 1)
        {
            return;
        }

        self::query()->create([
            'team_id' => $teamId,
            'kind' => $kind,
            'quantity' => $quantity,
        ]);
    }
}
