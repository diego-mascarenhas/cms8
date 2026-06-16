<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AffiliateInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'invited_by_user_id',
        'invitee_name',
        'invitee_email',
        'plan_id',
        'plan_name',
        'tracking_token',
        'sent_at',
        'opened_at',
        'clicked_at',
        'clicked_link',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public static function generateTrackingToken(): string
    {
        return Str::random(48);
    }

    public function markOpened(): void
    {
        if ($this->opened_at !== null)
        {
            return;
        }

        $this->forceFill(['opened_at' => now()])->save();
    }

    public function markClicked(string $linkType): void
    {
        $updates = [];
        if ($this->opened_at === null)
        {
            $updates['opened_at'] = now();
        }
        if ($this->clicked_at === null)
        {
            $updates['clicked_at'] = now();
            $updates['clicked_link'] = $linkType;
        }

        if ($updates !== [])
        {
            $this->forceFill($updates)->save();
        }
    }

    public function trackedOpenUrl(): string
    {
        return route('affiliate-invite.track.open', ['token' => $this->tracking_token]);
    }

    public function trackedClickUrl(string $originalUrl, string $linkType): string
    {
        return route('affiliate-invite.track.click', [
            'token' => $this->tracking_token,
            'url' => $originalUrl,
            'link' => $linkType,
        ]);
    }

    public function statusLabel(): string
    {
        if ($this->clicked_at !== null)
        {
            return match ($this->clicked_link)
            {
                'checkout' => 'Clic · Suscripción',
                'pricing' => 'Clic · Planes',
                default => 'Clic',
            };
        }

        if ($this->opened_at !== null)
        {
            return 'Abierto';
        }

        return 'Enviado';
    }

    public function statusBadgeClass(): string
    {
        if ($this->clicked_at !== null)
        {
            return 'bg-label-primary';
        }

        if ($this->opened_at !== null)
        {
            return 'bg-label-info';
        }

        return 'bg-label-success';
    }

    public function statusAt(): ?\Illuminate\Support\Carbon
    {
        return $this->clicked_at ?? $this->opened_at ?? $this->sent_at ?? $this->created_at;
    }
}
