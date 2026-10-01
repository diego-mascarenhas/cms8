<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Team;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeInvoicePdfRefresher
{
    public function freshPdfUrl(Invoice $invoice): ?string
    {
        $externalId = trim((string) $invoice->source_reference_id);
        if (! str_starts_with($externalId, 'in_') && ! str_starts_with($externalId, 'cn_'))
        {
            return null;
        }

        $team = Team::query()->find($invoice->team_id);
        $secret = trim((string) ($team?->getSetting('stripe_secret') ?? ''));
        if (! str_starts_with($secret, 'sk_') && ! str_starts_with($secret, 'rk_'))
        {
            return null;
        }

        try
        {
            $client = new StripeClient($secret);

            if (str_starts_with($externalId, 'cn_'))
            {
                $creditNote = $client->creditNotes->retrieve($externalId, []);

                return $this->urlOrNull($creditNote->pdf ?? null);
            }

            $stripeInvoice = $client->invoices->retrieve($externalId, []);

            return $this->urlOrNull($stripeInvoice->invoice_pdf ?? null);
        } catch (ApiErrorException)
        {
            return null;
        }
    }

    private function urlOrNull(mixed $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' ? $url : null;
    }
}
