<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'message_id',
        'contact_id',
        'campaign_id',
        'smtp_id',
        'sent_at',
        'delivered_at',
        'removed_at',
        'status_id',
        'scheduled_for',
        'email_provider',
        'provider_message_id',
        'delivery_status',
        'bounced_at',
        'opened_at',
        'clicked_at',
        'complained_at',
        'provider_data',
        'error_message',
        'error_type',
        'bounce_type',
        'bounce_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'removed_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'provider_data' => 'array',
    ];

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function contact()
    {
        return $this->belongsTo(\App\Models\Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function links()
    {
        return $this->hasMany(MessageDeliveryLink::class, 'message_delivery_id');
    }

    /**
     * Tracking events for this delivery
     */
    public function trackingEvents()
    {
        return $this->hasMany(MessageDeliveryTracking::class, 'message_delivery_id');
    }

    /**
     * Generate a tracking token for this delivery
     */
    public function getTrackingToken()
    {
        return hash('sha256', config('app.key').$this->id);
    }

    /**
     * Get the tracking URL for open events
     */
    public function getTrackingUrl()
    {
        return route('message.track', ['token' => $this->getTrackingToken()]);
    }

    /**
     * Get a tracked URL for click events
     */
    public function getTrackedUrl($originalUrl)
    {
        return route('message.track.click', ['token' => $this->getTrackingToken()]).'?url='.urlencode($originalUrl);
    }

    /**
     * Mark as sent (status_id = 1)
     */
    public function markAsSent()
    {
        $this->update([
            'sent_at' => now(),
            'status_id' => 1, // 1 = sent
        ]);
    }

    /**
     * Mark as delivered (status_id = 2)
     */
    public function markAsDelivered()
    {
        $this->update([
            'delivered_at' => now(),
            'status_id' => 2, // 2 = delivered
        ]);
    }

    /**
     * Mark as opened (status_id = 2)
     * Note: Tracking events are now handled in MessageTrackingController to avoid duplication
     */
    public function markAsOpened()
    {
        \Log::info('Trying to mark as opened', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        if (! $this->opened_at)
        {
            $this->update([
                'opened_at' => now(),
                'status_id' => 2, // 2 = opened
            ]);
            \Log::info('Marked as opened', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        } else
        {
            \Log::info('Already opened', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        }
    }

    /**
     * Mark as clicked (status_id = 3)
     */
    public function markAsClicked()
    {
        // Only update clicked_at if it's the first click
        if (! $this->clicked_at)
        {
            $this->update([
                'clicked_at' => now(),
                'status_id' => 3, // 3 = clicked
            ]);
        }
    }

    /**
     * Mark as error (status_id = 4)
     */
    public function markAsError(?string $errorMessage = null)
    {
        // Only set sent_at if it wasn't already set (actual send attempt was made)
        if (! $this->sent_at)
        {
            $this->sent_at = now();
        }
        $this->status_id = 4; // 4 = error
        $this->error_type = 'smtp_error'; // SMTP/sending error (not a bounce)
        $this->error_message = $errorMessage;

        // Store error message in provider_data for debugging
        if ($errorMessage)
        {
            $providerData = $this->provider_data ?? [];
            $providerData['error'] = $errorMessage;
            $providerData['error_time'] = now()->toISOString();
            $this->provider_data = $providerData;
        }

        $this->save();

        // Check if this is a critical error and handle campaign pausing
        if ($errorMessage && $this->message)
        {
            $this->message->handleCriticalError($errorMessage, $this->id);
        }
    }

    /**
     * Status badge for UI
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->opened_at)
        {
            return '<span class="badge bg-info">Opened</span>';
        }
        if ($this->sent_at)
        {
            return '<span class="badge bg-success">Sent</span>';
        }

        return '<span class="badge bg-warning">Pending</span>';
    }

    /**
     * Generate personalized HTML for the contact using the associated message template
     */
    public function getHtmlForContact()
    {
        $templateHtml = '';
        if ($this->message)
        {
            $templateHtml = $this->message->resolveMailHtml();
        }

        if (trim($templateHtml) === '')
        {
            $templateHtml = '<p>'.e($this->message?->text ?? '').'</p>';
        }

        // Replace all template variables
        $html = $this->replaceEmailVariables($templateHtml, $this->contact, $this->message);

        // Rewrite URLs for click tracking (only for SMTP emails)
        if ($this->shouldEnableClickTracking())
        {
            $html = \App\Helpers\EmailTrackingHelper::rewriteUrlsForTracking($html, $this);
        }

        // Add unsubscribe link
        $html = \App\Helpers\EmailTrackingHelper::addUnsubscribeLink($html, $this);

        // Add tracking pixel for open tracking
        $html = \App\Helpers\EmailTrackingHelper::addTrackingPixel($html, $this);

        // Get team to check if advertising footer should be added
        $team = $this->message && $this->message->team ? $this->message->team : auth()->user()->currentTeam;

        // Add advertising footer if using system SMTP
        $advertisingFooter = $team ? $team->getAdvertisingFooter() : '';

        // Insert advertising footer before </body> or at the end
        if (stripos($html, '</body>') !== false)
        {
            $html = str_ireplace('</body>', $advertisingFooter.'</body>', $html);
        } else
        {
            $html .= $advertisingFooter;
        }

        return $html;
    }

    /**
     * Check if click tracking should be enabled for this delivery
     */
    private function shouldEnableClickTracking(): bool
    {
        // Enable click tracking for SMTP emails (not for providers that handle it themselves)
        return in_array($this->email_provider, ['smtp', null]) ||
               config('services.email.provider', 'smtp') === 'smtp';
    }

    /**
     * Generate personalized text for WhatsApp campaigns
     */
    public function getTextForWhatsApp(): string
    {
        $messageText = $this->message && $this->message->text
            ? $this->message->text
            : 'Mensaje de prueba';

        $contactName = $this->contact ? $this->contact->name : '';

        // Simple variable replacement for {{name}}
        return str_replace('{{name}}', $contactName, $messageText);
    }

    /**
     * Replace email template variables with actual values
     */
    private function replaceEmailVariables(string $htmlContent, $contact, $message = null): string
    {
        // Basic contact variables
        $htmlContent = str_replace('{{name}}', $contact->name ?? '', $htmlContent);
        $htmlContent = str_replace('{{contact_name}}', ($contact->name ?? '').' '.($contact->surname ?? ''), $htmlContent);
        $htmlContent = str_replace('{{email}}', $contact->email ?? '', $htmlContent);

        // Note: {{date}} and {{header}} variables have been removed from templates
        // They are now hardcoded in the template content

        return $htmlContent;
    }
}
