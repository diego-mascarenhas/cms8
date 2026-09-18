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
        ])->save();
    }

    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => CommunicationStatus::Failed,
            'error_message' => $message,
        ])->save();
    }

    public function isFailed(): bool
    {
        return $this->status === CommunicationStatus::Failed;
    }
}
