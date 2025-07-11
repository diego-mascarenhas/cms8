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

    /**
     * Get formatted created date
     */
    public function getFormattedCreatedDateAttribute()
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    /**
     * Get avatar color based on notification type
     */
    public function getAvatarColor()
    {
        if ($this->type) {
            // Map notification type names to colors
            $colorMap = [
                'Project Assignment' => 'primary',
                'Project Update' => 'info',
                'General Message' => 'secondary',
                'Payment Reminder' => 'warning',
                'Task Assignment' => 'success',
                'Welcome Message' => 'primary',
            ];

            return $colorMap[$this->type->name] ?? 'secondary';
        }

        return 'secondary';
    }

    /**
     * Get avatar initials
     */
    public function getAvatarInitials()
    {
        if ($this->user) {
            $names = explode(' ', $this->user->name);
            $initials = '';

            foreach ($names as $name) {
                $initials .= strtoupper(substr($name, 0, 1));
                if (strlen($initials) >= 2) {
                    break;
                }
            }

            return $initials ?: 'UN';
        }

        if ($this->type) {
            return strtoupper(substr($this->type->name, 0, 2));
        }

        return 'NO';
    }

    /**
     * Generate a tracking token based on the notification ID
     */
    public function getTrackingToken()
    {
        return hash('sha256', config('app.key') . $this->id);
    }

    /**
     * Get the tracking URL for this notification
     */
    public function getTrackingUrl()
    {
        return route('notification.track', ['token' => $this->getTrackingToken()]);
    }

    /**
     * Generate a tracked URL for click tracking
     */
    public function getTrackedUrl($originalUrl)
    {
        return route('notification.track.click', ['token' => $this->getTrackingToken()]) . '?url=' . urlencode($originalUrl);
    }

    /**
     * Find notification by tracking token
     */
    public static function findByTrackingToken($token)
    {
        // We need to find the notification by checking all notifications
        // This is not the most efficient for large datasets, but it's secure
        return self::get()->first(function ($notification) use ($token) {
            return hash_equals($notification->getTrackingToken(), $token);
        });
    }

    /**
     * Get tracking events for this notification
     */
    public function trackingEvents()
    {
        return $this->hasMany(NotificationTracking::class);
    }
}
