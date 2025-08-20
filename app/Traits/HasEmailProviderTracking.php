<?php

namespace App\Traits;

trait HasEmailProviderTracking
{
    /**
     * Check if this delivery was sent via a specific provider
     */
    public function wasSentVia($provider)
    {
        return $this->email_provider === $provider;
    }

    /**
     * Check if this delivery was sent via MailBaby
     */
    public function wasSentViaMailBaby()
    {
        return $this->wasSentVia('mailbaby');
    }

    /**
     * Check if this delivery was sent via SMTP
     */
    public function wasSentViaSmtp()
    {
        return $this->wasSentVia('smtp');
    }

    /**
     * Check if this delivery was sent via SendGrid
     */
    public function wasSentViaSendGrid()
    {
        return $this->wasSentVia('sendgrid');
    }

    /**
     * Check if this delivery was sent via Mailgun
     */
    public function wasSentViaMailgun()
    {
        return $this->wasSentVia('mailgun');
    }

    /**
     * Check if this delivery has real delivery tracking (vs simulated)
     */
    public function hasRealTracking()
    {
        return in_array($this->email_provider, ['mailbaby', 'sendgrid', 'mailgun']);
    }

    /**
     * Check if this delivery uses simulated tracking
     */
    public function usesSimulatedTracking()
    {
        return !$this->hasRealTracking();
    }

    /**
     * Get the provider's dashboard URL for this message (if available)
     */
    public function getProviderDashboardUrl()
    {
        if (!$this->provider_message_id) {
            return null;
        }

        switch ($this->email_provider) {
            case 'mailbaby':
                return "https://mail.baby/dashboard/messages/{$this->provider_message_id}";
            case 'sendgrid':
                return "https://app.sendgrid.com/email_activity?search={$this->provider_message_id}";
            case 'mailgun':
                return "https://app.mailgun.com/app/sending/domains/DOMAIN/logs?query={$this->provider_message_id}";
            default:
                return null;
        }
    }

    /**
     * Get delivery status badge color
     */
    public function getDeliveryStatusColor()
    {
        switch ($this->delivery_status) {
            case 'delivered':
                return 'success';
            case 'bounced':
            case 'failed':
                return 'danger';
            case 'sent':
                return 'warning';
            default:
                return 'secondary';
        }
    }

    /**
     * Get delivery status icon
     */
    public function getDeliveryStatusIcon()
    {
        switch ($this->delivery_status) {
            case 'delivered':
                return 'ti-check';
            case 'bounced':
                return 'ti-alert-triangle';
            case 'failed':
                return 'ti-x';
            case 'sent':
                return 'ti-clock';
            default:
                return 'ti-help';
        }
    }

    /**
     * Check if this email was opened
     */
    public function wasOpened()
    {
        return !is_null($this->opened_at);
    }

    /**
     * Check if this email was clicked
     */
    public function wasClicked()
    {
        return !is_null($this->clicked_at);
    }

    /**
     * Check if this email bounced
     */
    public function wasBounced()
    {
        return !is_null($this->bounced_at);
    }

    /**
     * Get engagement score (0-100)
     */
    public function getEngagementScore()
    {
        $score = 0;

        if ($this->delivery_status === 'delivered') {
            $score += 50; // Base score for delivery
        }

        if ($this->wasOpened()) {
            $score += 30; // Additional for opens
        }

        if ($this->wasClicked()) {
            $score += 20; // Additional for clicks
        }

        return min($score, 100);
    }
}
