<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageDelivery extends Model
{
	use HasFactory;

	protected $fillable = [
		'team_id',
		'message_id',
		'contact_id',
		'smtp_id',
		'sent_at',
		'delivered_at',
		'removed_at',
		'status_id',
	];

	protected $casts = [
		'sent_at' => 'datetime',
		'delivered_at' => 'datetime',
		'removed_at' => 'datetime',
	];

	public function team()
	{
		return $this->belongsTo(Team::class);
	}

	public function message()
	{
		return $this->belongsTo(Message::class);
	}

	public function contact()
	{
		return $this->belongsTo(Contact::class);
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
        return hash('sha256', config('app.key') . $this->id);
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
        return route('message.track.click', ['token' => $this->getTrackingToken()]) . '?url=' . urlencode($originalUrl);
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
        if (!$this->opened_at) {
            $this->update([
                'opened_at' => now(),
                'status_id' => 2, // 2 = opened
            ]);
            \Log::info('Marked as opened', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        } else {
            \Log::info('Already opened', ['id' => $this->id, 'opened_at' => $this->opened_at]);
        }
    }

    /**
     * Mark as clicked (status_id = 3)
     */
    public function markAsClicked()
    {
        $this->update([
            'status_id' => 3, // 3 = clicked
        ]);
    }

    /**
     * Mark as error (status_id = 4)
     */
    public function markAsError()
    {
        $this->sent_at = now();
        $this->status_id = 4; // 4 = error
        $this->save();
    }

    /**
     * Status badge for UI
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->opened_at) {
            return '<span class="badge bg-info">Opened</span>';
        }
        if ($this->sent_at) {
            return '<span class="badge bg-success">Sent</span>';
        }
        return '<span class="badge bg-warning">Pending</span>';
    }

    /**
     * Generate personalized HTML for the contact using the associated message template
     */
    public function getHtmlForContact()
    {
        $templateHtml = $this->message && $this->message->template && isset($this->message->template->gjs_data['html'])
            ? $this->message->template->gjs_data['html']
            : '';
        $contactName = $this->contact ? $this->contact->name : '';
        
        // Simple variable replacement for {{name}}
        $html = str_replace('{{name}}', $contactName, $templateHtml);
        
        // Get team to check if advertising footer should be added
        $team = $this->message && $this->message->team ? $this->message->team : auth()->user()->currentTeam;
        
        // Add advertising footer if using system SMTP
        $advertisingFooter = $team ? $team->getAdvertisingFooter() : '';
        
        // Insert tracking image and advertising footer before </body> or at the end
        $trackingImg = '<img src="' . $this->getTrackingUrl() . '" width="1" height="1" style="display:none;" alt="" />';
        $insertContent = $advertisingFooter . $trackingImg;
        
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $insertContent . '</body>', $html);
        } else {
            $html .= $insertContent;
        }
        
        return $html;
    }
}
