<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'messages';

    protected $fillable = ['name', 'type_id', 'category_id', 'contact_status_id', 'template_id', 'text', 'status_id', 'show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking', 'min_hours_between_emails', 'team_id', 'started_at'];

    protected $casts = [
        'status_id' => 'boolean',
        'show_unsubscribe' => 'boolean',
        'enable_open_tracking' => 'boolean',
        'enable_click_tracking' => 'boolean',
        'min_hours_between_emails' => 'integer',
        'started_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope('team', function (Builder $builder)
        {
            if (auth()->check())
            {
                $builder->where('team_id', auth()->user()->currentTeam->id);
            }
        });

        static::creating(function ($model)
        {
            if (! $model->team_id && auth()->check())
            {
                $model->team_id = auth()->user()->currentTeam->id;
            }
        });
    }

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function type()
    {
        return $this->belongsTo(MessageType::class);
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }

    public function template()
    {
        return $this->belongsTo(\App\Models\Template::class);
    }

    public function deliveries()
    {
        return $this->hasMany(MessageDelivery::class);
    }

    public function contactStatus()
    {
        return $this->belongsTo(\App\Models\ContactStatus::class);
    }

    /**
     * Check if this message can be sent to a specific contact based on the minimum hours between emails
     */
    public function canSendToContact(\App\Models\Contact $contact): bool
    {
        // If min_hours_between_emails is 0, always allow sending
        if ($this->min_hours_between_emails <= 0)
        {
            return true;
        }

        // Get the last email sent to this contact from any message in the same team
        $lastDelivery = MessageDelivery::where('contact_id', $contact->id)
            ->where('team_id', $this->team_id)
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->first();

        // If no previous email was sent, allow sending
        if (! $lastDelivery)
        {
            return true;
        }

        // Calculate hours since last email
        $hoursSinceLastEmail = now()->diffInHours($lastDelivery->sent_at);

        // Check if enough time has passed
        return $hoursSinceLastEmail >= $this->min_hours_between_emails;
    }

    /**
     * Get the next available time to send an email to a specific contact
     */
    public function getNextAvailableTimeForContact(\App\Models\Contact $contact): ?\Carbon\Carbon
    {
        // If min_hours_between_emails is 0, can send immediately
        if ($this->min_hours_between_emails <= 0)
        {
            return now();
        }

        // Get the last email sent to this contact
        $lastDelivery = MessageDelivery::where('contact_id', $contact->id)
            ->where('team_id', $this->team_id)
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->first();

        // If no previous email was sent, can send immediately
        if (! $lastDelivery)
        {
            return now();
        }

        // Calculate next available time
        return $lastDelivery->sent_at->addHours($this->min_hours_between_emails);
    }

    /**
     * Check if an error message indicates a critical system error
     */
    public static function isCriticalError(string $errorMessage): bool
    {
        $criticalPatterns = [
            // SPF Errors
            'SPF',
            '550 5.7.0',
            'domain is not configured with ORIGIN IP',
            '5.7.1 Service unavailable; Client host',

            // DNS Errors
            'DNS',
            'SERVFAIL',
            'NXDOMAIN',
            'Name or service not known',

            // Authentication Errors
            '535',
            'authentication',
            'login',
            'Invalid credentials',
            'Unauthorized',
            'API key',

            // Mail server errors
            'Connection refused',
            'Connection timed out',
            'Host not found',
            'Mail server temporarily rejected',
            'Relay access denied',
        ];

        foreach ($criticalPatterns as $pattern)
        {
            if (stripos($errorMessage, $pattern) !== false)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Count recent critical errors for this message (last 10 minutes)
     */
    public function getRecentCriticalErrorsCount(): int
    {
        return MessageDelivery::where('message_id', $this->id)
            ->where('status_id', 4) // error status
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->count();
    }

    /**
     * Check if campaign should be paused due to critical errors
     */
    public function shouldPauseForErrors(): bool
    {
        $recentErrors = $this->getRecentCriticalErrorsCount();
        $threshold = 3; // Pause after 3+ critical errors in 10 minutes

        return $recentErrors >= $threshold;
    }

    /**
     * Pause campaign due to critical errors
     */
    public function pauseForErrors(string $reason = 'Critical errors detected'): void
    {
        $this->update([
            'status_id' => 0, // inactive/paused
        ]);

        // Log the pause
        \Log::warning('📛 Campaign paused automatically', [
            'message_id' => $this->id,
            'message_name' => $this->name,
            'team_id' => $this->team_id,
            'reason' => $reason,
            'recent_errors' => $this->getRecentCriticalErrorsCount(),
        ]);
    }

    /**
     * Handle a critical error from a delivery
     */
    public function handleCriticalError(string $errorMessage, ?int $deliveryId = null): void
    {
        if (! $this->isCriticalError($errorMessage))
        {
            return;
        }

        \Log::error('🚨 Critical error detected in campaign', [
            'message_id' => $this->id,
            'message_name' => $this->name,
            'delivery_id' => $deliveryId,
            'error' => $errorMessage,
            'recent_errors_before' => $this->getRecentCriticalErrorsCount(),
        ]);

        // Check if we should pause after this error
        if ($this->shouldPauseForErrors())
        {
            $this->pauseForErrors('Critical error: '.substr($errorMessage, 0, 100));
        }
    }
}
