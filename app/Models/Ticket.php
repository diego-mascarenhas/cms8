<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'team_id',
        'user_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'closed_at',
        'last_response_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'last_response_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $teamId = optional(auth()->user()->currentTeam)->id
                    ?? auth()->user()->teams()->value('teams.id');
                if ($teamId)
                {
                    $builder->where('team_id', $teamId);
                }
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responses()
    {
        return $this->hasMany(TicketResponse::class)->orderBy('created_at', 'asc');
    }

    public function rating()
    {
        return $this->hasOne(TicketRating::class);
    }

    public function isOpen(): bool
    {
        return $this->status !== 'closed';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function getStatusLabelAttribute(): string
    {
        $key = 'tickets.status_'.$this->status;

        return __($key) !== $key ? __($key) : ucfirst($this->status);
    }

    public function getPriorityLabelAttribute(): string
    {
        $key = 'tickets.priority_'.$this->priority;

        return __($key) !== $key ? __($key) : ucfirst($this->priority);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status)
        {
            'open' => 'warning',
            'in_progress' => 'primary',
            'waiting_client' => 'info',
            'closed' => 'success',
            default => 'secondary',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority)
        {
            'low' => 'secondary',
            'medium' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
            default => 'secondary',
        };
    }

    public function updateLastResponse(): void
    {
        $this->update(['last_response_at' => now()]);
    }

    public function getHoursSinceLastResponseAttribute(): float
    {
        $reference = $this->last_response_at ?? $this->created_at;

        return round($reference->diffInMinutes(now()) / 60, 1);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'application/zip',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }
}
