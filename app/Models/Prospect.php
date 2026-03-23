<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prospect extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'channel',
        'external_id',
        'onboarding_step',
        'data',
        'converted_at',
        'team_id',
        'user_id',
    ];

    protected $casts = [
        'data' => 'array',
        'converted_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    /**
     * Capture or update a prospect when someone writes to the demo assistant (WhatsApp).
     */
    public static function captureFromWhatsApp(string $fromNormalized, ?int $teamId = null): self
    {
        $prospect = self::query()->firstOrCreate(
            [
                'channel' => self::CHANNEL_WHATSAPP,
                'external_id' => $fromNormalized,
            ],
            [
                'onboarding_step' => null,
                'data' => ['whatsapp_from' => $fromNormalized],
                'team_id' => $teamId,
            ],
        );

        $data = $prospect->data ?? [];
        $data['whatsapp_from'] = $fromNormalized;
        $data['last_contact_at'] = now()->toIso8601String();
        $prospect->forceFill([
            'data' => $data,
            'team_id' => $teamId ?? $prospect->team_id,
        ])->save();

        return $prospect;
    }
}
