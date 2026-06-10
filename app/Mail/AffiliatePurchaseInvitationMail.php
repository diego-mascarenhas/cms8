<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AffiliatePurchaseInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $planFeatures
     */
    public function __construct(
        public Team $referrerTeam,
        public User $invitedBy,
        public string $inviteeName,
        public string $planName,
        public string $planDescription,
        public array $planFeatures,
        public string $planImageUrl,
        public string $checkoutUrl,
        public string $pricingUrl,
    ) {}

    public function build(): self
    {
        $inviterLabel = $this->resolveInviterLabel();
        $mail = $this->subject("{$inviterLabel} te invita a conocer Humano — {$this->planName}")
            ->view('emails.affiliate-purchase-invitation', [
                'inviterLabel' => $inviterLabel,
                'logoUrl' => url(Helpers::logoAsset('dark')),
                'appName' => (string) config('app.name'),
            ]);

        $fromAddress = trim((string) ($this->referrerTeam->getSetting('mail_from_address') ?? ''));
        $fromName = trim((string) ($this->referrerTeam->getSetting('mail_from_name') ?? ''));

        if ($fromAddress !== '')
        {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : $this->referrerTeam->name);
        }

        return $mail;
    }

    private function resolveInviterLabel(): string
    {
        $fromName = trim((string) ($this->referrerTeam->getSetting('mail_from_name') ?? ''));
        if ($fromName !== '')
        {
            return $fromName;
        }

        $userName = trim((string) ($this->invitedBy->name ?? ''));
        if ($userName !== '')
        {
            return $userName;
        }

        return $this->referrerTeam->name;
    }
}
