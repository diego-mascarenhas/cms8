<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'type_id',
        'contact_id',
        'user_id',
        'reference',
        'subject',
        'message',
        'is_sent',
        'sent_at',
        'sent_data',
        'is_read',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
        'sent_data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });
    }

    /**
     * Get the team that owns the notification
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the notification type
     */
    public function type()
    {
        return $this->belongsTo(NotificationType::class, 'type_id');
    }

    /**
     * Get the contact that received the notification
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who sent the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(array $sentData = [])
    {
        $this->update([
            'is_sent' => true,
            'sent_at' => now(),
            'sent_data' => $sentData,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('is_sent', true);
    }

    /**
     * Scope for unsent notifications
     */
    public function scopeUnsent($query)
    {
        return $query->where('is_sent', false);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted sent date
     */
    public function getFormattedSentDateAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('d/m/Y H:i') : 'No enviado';
    }

    /**
     * Get status badge HTML
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->is_sent) {
            return '<span class="badge bg-success">Enviado</span>';
        }
        
        return '<span class="badge bg-warning">Pendiente</span>';
    }

    /**
     * Get read status badge HTML
     */
    public function getReadStatusBadgeAttribute()
    {
        if ($this->is_read) {
            return '<span class="badge bg-info">Leído</span>';
        }
        
        return '<span class="badge bg-secondary">No leído</span>';
    }
} 