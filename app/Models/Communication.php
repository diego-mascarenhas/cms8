<?php

namespace App\Models;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property CommunicationChannel $channel
 * @property CommunicationStatus $status
 */
class Communication extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'team_id',
        'user_id',
        'contact_id',
        'channel',
        'recipient_email',
        'recipient_phone',
        'recipient_name',
        'subject',
        'message',
        'status',
        'sent_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'channel' => CommunicationChannel::class,
        'status' => CommunicationStatus::class,
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check() && auth()->user()->currentTeam)
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withoutGlobalScopes();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('public');
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => CommunicationStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
            'metadata' => $this->withEvent('sent'),
        ])->save();

        $this->recordMailerUsageIfEmail();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => CommunicationStatus::Failed,
            'error_message' => $message,
            'metadata' => $this->withEvent('failed', $message),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function withEvent(string $type, ?string $message = null): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $events = isset($metadata['events']) && is_array($metadata['events'])
            ? $metadata['events']
            : [];

        if ($events === [])
        {
            $events[] = [
                'type' => 'queued',
                'at' => $this->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'message' => null,
            ];
        }

        $events[] = [
            'type' => $type,
            'at' => now()->toIso8601String(),
            'message' => $message,
        ];

        $metadata['events'] = $events;

        return $metadata;
    }

    public function isFailed(): bool
    {
        return $this->status === CommunicationStatus::Failed;
    }

    public function canResend(): bool
    {
        return $this->status === CommunicationStatus::Sent
            || $this->status === CommunicationStatus::Failed;
    }

    public function trackingToken(): string
    {
        $signature = substr(hash_hmac('sha256', 'c-open-'.$this->id, (string) config('app.key')), 0, 32);

        return $this->id.'.'.$signature;
    }

    public function trackingUrl(): string
    {
        return route('communications.track.open', ['token' => $this->trackingToken()]);
    }

    public static function findByTrackingToken(string $token): ?self
    {
        if (! preg_match('/^(\d+)\.([a-f0-9]{32})$/', $token, $matches))
        {
            return null;
        }

        $communication = static::withoutGlobalScopes()->find((int) $matches[1]);
        if (! $communication || ! hash_equals($communication->trackingToken(), $token))
        {
            return null;
        }

        return $communication;
    }

    public function hasOpened(): bool
    {
        foreach ($this->events() as $event)
        {
            if (($event['type'] ?? '') === 'opened')
            {
                return true;
            }
        }

        return false;
    }

    public function markOpened(?string $message = null): void
    {
        if ($this->hasOpened())
        {
            return;
        }

        $this->forceFill([
            'metadata' => $this->withEvent('opened', $message),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function storeProviderTracking(string $provider, ?string $messageId, array $extra = []): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['email_provider'] = $provider;
        if (is_string($messageId) && $messageId !== '')
        {
            $metadata['provider_message_id'] = $messageId;
        }

        foreach ($extra as $key => $value)
        {
            $metadata[$key] = $value;
        }

        $this->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        $events = is_array($this->metadata) ? ($this->metadata['events'] ?? []) : [];

        return is_array($events) ? array_values($events) : [];
    }

    private function recordMailerUsageIfEmail(): void
    {
        if ($this->channel !== CommunicationChannel::Email || ! $this->team_id)
        {
            return;
        }

        MailerUsageLog::query()->create([
            'team_id' => $this->team_id,
            'source' => 'communications',
            'count' => 1,
            'sent_at' => $this->sent_at ?? now(),
        ]);
    }
}
