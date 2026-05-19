<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'messages';

    protected $fillable = ['name', 'type_id', 'category_id', 'contact_status_id', 'template_id', 'text', 'mail_html', 'status_id', 'show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking', 'min_hours_between_emails', 'send_allowed_weekdays', 'send_window_start', 'send_window_end', 'team_id', 'started_at', 'scheduled_send_at'];

    protected $casts = [
        'status_id' => 'boolean',
        'show_unsubscribe' => 'boolean',
        'enable_open_tracking' => 'boolean',
        'enable_click_tracking' => 'boolean',
        'min_hours_between_emails' => 'integer',
        'send_allowed_weekdays' => 'array',
        'started_at' => 'datetime',
        'scheduled_send_at' => 'datetime',
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

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_message')
            ->withTimestamps();
    }

    public function contactStatus()
    {
        return $this->belongsTo(\App\Models\ContactStatus::class);
    }

    public function resolveMailHtml(): string
    {
        if (is_string($this->mail_html) && trim($this->mail_html) !== '')
        {
            return $this->mail_html;
        }

        if ($this->relationLoaded('template') || $this->template_id)
        {
            $template = $this->template;
            if ($template && is_array($template->gjs_data) && isset($template->gjs_data['html']))
            {
                return (string) $template->gjs_data['html'];
            }
        }

        return '';
    }

    public function resolveMailCss(): string
    {
        $template = $this->template;
        if ($template && is_array($template->gjs_data) && isset($template->gjs_data['css']))
        {
            return (string) $template->gjs_data['css'];
        }

        return '';
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
     * Whether this message restricts sending days or intra-day sending hours.
     */
    public function hasSendingScheduleConstraints(): bool
    {
        return $this->send_allowed_weekdays !== null
            || ($this->send_window_start !== null && $this->send_window_end !== null);
    }

    /**
     * Normalize allowed ISO weekdays (Monday=1 … Sunday=7). Null in storage means unrestricted (all weekdays).
     *
     * @return array<int, int>
     */
    public function normalizedAllowedWeekdayIsos(): array
    {
        if ($this->send_allowed_weekdays === null || $this->send_allowed_weekdays === [])
        {
            return range(1, 7);
        }

        return array_values(array_unique(array_map('intval', $this->send_allowed_weekdays)));
    }

    public function sendingWindowConfigured(): bool
    {
        return $this->send_window_start !== null
            && $this->send_window_start !== ''
            && $this->send_window_end !== null
            && $this->send_window_end !== '';
    }

    public function minuteOfDay(Carbon $moment): int
    {
        return ($moment->hour * 60) + $moment->minute;
    }

    public function parseTimeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, 0);

        return ((int) $h * 60) + (int) $m;
    }

    public function momentIsWithinSendingWindow(Carbon $moment): bool
    {
        if (! $this->sendingWindowConfigured())
        {
            return true;
        }

        $nowM = $this->minuteOfDay($moment);
        $startM = $this->parseTimeToMinutes($this->send_window_start);
        $endM = $this->parseTimeToMinutes($this->send_window_end);

        return $nowM >= $startM && $nowM <= $endM;
    }

    /**
     * Adjust a candidate send time so it falls on an allowed weekday and within the optional daily time window (app timezone).
     */
    public function alignScheduledTimeWithSendingSchedule(Carbon $candidate): Carbon
    {
        $t = $candidate->copy()->timezone((string) config('app.timezone'));

        if (! $this->hasSendingScheduleConstraints())
        {
            return $t;
        }

        $allowed = $this->normalizedAllowedWeekdayIsos();
        $usesWindow = $this->sendingWindowConfigured();

        for ($i = 0; $i < 21; $i++)
        {
            if (! in_array((int) $t->isoWeekday(), $allowed, true))
            {
                $t->addDay();

                continue;
            }

            if (! $usesWindow)
            {
                return $t;
            }

            if ($this->momentIsWithinSendingWindow($t))
            {
                return $t;
            }

            $startM = $this->parseTimeToMinutes((string) $this->send_window_start);
            $m = $this->minuteOfDay($t);

            if ($m < $startM)
            {
                $this->applyWindowStartToMoment($t);

                return $t;
            }

            // Past end-of-window → try next day's window opening
            $t->addDay()->startOfDay();
            $this->applyWindowStartToMoment($t);
        }

        return $t;
    }

    private function applyWindowStartToMoment(Carbon $t): void
    {
        if (! $this->sendingWindowConfigured())
        {
            return;
        }

        $t->setTimeFromTimeString($this->send_window_start);
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
