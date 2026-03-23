<?php

namespace App\Support;

class StripeInvoiceMetrics
{
    /**
     * Display currency code for aggregated invoice amounts (Stripe uses lowercase ISO codes).
     *
     * @param  array<int, object>  $invoices
     */
    public static function displayCurrencyForInvoices(array $invoices, string $default = 'EUR'): string
    {
        if ($invoices === [])
        {
            return $default;
        }

        $currency = $invoices[0]->currency ?? $default;

        return strtoupper((string) $currency);
    }

    /**
     * Prefer currency from unpaid invoices (open + uncollectible) so dashboard labels match pending balances.
     * If the contact is in Argentina and any invoice is in ARS, prefer ARS for the label when still ambiguous.
     *
     * @param  array<int, object>  $paidInvoices
     * @param  array<int, object>  $openInvoices
     * @param  array<int, object>  $uncollectibleInvoices
     */
    public static function displayCurrencyForStripeInvoiceGroups(
        array $paidInvoices,
        array $openInvoices,
        array $uncollectibleInvoices,
        string $default = 'EUR',
        ?string $contactCountryCode = null,
    ): string {
        foreach (array_merge($openInvoices, $uncollectibleInvoices) as $invoice)
        {
            if (isset($invoice->currency) && $invoice->currency !== '')
            {
                return strtoupper((string) $invoice->currency);
            }
        }

        $merged = array_merge($paidInvoices, $openInvoices, $uncollectibleInvoices);
        if ($merged === [])
        {
            return $default;
        }

        $fromFirst = strtoupper((string) ($merged[0]->currency ?? $default));

        if ($contactCountryCode === 'ar')
        {
            foreach ($merged as $invoice)
            {
                if (isset($invoice->currency) && strtoupper((string) $invoice->currency) === 'ARS')
                {
                    return 'ARS';
                }
            }
        }

        return $fromFirst;
    }
}
