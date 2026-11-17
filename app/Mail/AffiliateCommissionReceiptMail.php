<?php

namespace App\Mail;

use App\Helpers\Helpers;
use App\Models\BillingAffiliateCommission;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AffiliateCommissionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BillingAffiliateCommission $commission,
        public Team $referrerTeam,
        public Team $payingTeam,
        public string $serviceLabel,
        public string $receiptNumber,
    ) {
        $this->locale = 'es_ES';
    }

    public function build(): self
    {
        $amountPaid = $this->formatMoney(
            (int) $this->commission->amount_paid_cents,
            (string) $this->commission->currency,
        );
        $commissionAmount = $this->formatMoney(
            (int) $this->commission->commission_amount_cents,
            (string) $this->commission->currency,
        );
        $percent = rtrim(rtrim(number_format((float) $this->commission->commission_percent, 2, '.', ''), '0'), '.');

        return $this->locale('es_ES')
            ->subject(__('affiliate_commission_receipt.subject', [
                'receipt' => $this->receiptNumber,
                'app' => (string) config('app.name'),
            ]))
            ->view('emails.affiliate-commission-receipt', [
                'logoUrl' => url(Helpers::logoAsset('light')),
                'appName' => (string) config('app.name'),
                'referrerTeamName' => $this->referrerTeam->name,
                'payingTeamName' => $this->payingTeam->name,
                'serviceLabel' => $this->serviceLabel,
                'receiptNumber' => $this->receiptNumber,
                'amountPaid' => $amountPaid,
                'commissionAmount' => $commissionAmount,
                'commissionPercent' => $percent,
                'currency' => strtoupper((string) $this->commission->currency),
                'recordedAt' => $this->commission->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
                'stripeInvoiceId' => (string) $this->commission->stripe_invoice_id,
            ]);
    }

    private function formatMoney(int $cents, string $currency): string
    {
        $amount = number_format($cents / 100, 2, ',', '.');
        $currency = strtoupper(trim($currency));

        return $currency !== '' ? $amount.' '.$currency : $amount;
    }
}
