<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\ServiceSync;
use App\Services\Finance\InvoiceSummaryService;
use Illuminate\Support\Collection;

class ClientHeadlineMetrics
{
    /**
     * Summary cards for the client page header.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @param  Collection<int, ServiceSync>  $subscriptions
     * @param  array{brand: string, last4: string, exp_month: int, exp_year: int}|null  $paymentCard
     * @return list<array{kicker: string, title: string, value: string, hint: string, icon: string, tone: string}>
     */
    public static function cards(Collection $invoices, Collection $subscriptions, ?array $paymentCard = null): array
    {
        $collected = [];
        $owed = [];

        foreach ($invoices as $invoice)
        {
            if (! $invoice instanceof Invoice || self::isCreditNote($invoice) || ! self::isSell($invoice))
            {
                continue;
            }

            $currency = $invoice->currency_code;
            $total = (float) $invoice->total_amount;
            $balance = (float) $invoice->balance;
            $paid = max(0, $total - $balance);
            if ($paid > 0)
            {
                $collected[$currency] = ($collected[$currency] ?? 0) + $paid;
            }

            if ($balance > 0 && ! in_array((int) $invoice->status, InvoiceSummaryService::UNPAID_EXCLUDED_STATUSES, true))
            {
                $owed[$currency] = ($owed[$currency] ?? 0) + $balance;
            }
        }

        $payment = self::paymentCard($subscriptions, $paymentCard);

        return [
            [
                'kicker' => 'CAC',
                'title' => 'Coste de adquisición',
                'value' => '0 €',
                'hint' => 'Sin coste de adquisición cargado',
                'icon' => 'ti-target',
                'tone' => 'secondary',
            ],
            [
                'kicker' => 'LTV',
                'title' => 'Valor del tiempo de vida',
                'value' => self::money($collected),
                'hint' => 'Cobrado en facturas de venta',
                'icon' => 'ti-currency-euro',
                'tone' => 'success',
            ],
            [
                'kicker' => 'Debe',
                'title' => 'Saldo pendiente',
                'value' => self::money($owed),
                'hint' => $owed === [] ? 'Nada pendiente de cobro' : 'Facturas de venta abiertas',
                'icon' => 'ti-receipt',
                'tone' => $owed === [] ? 'success' : 'warning',
            ],
            [
                'kicker' => 'Medio de pago',
                'title' => 'Cómo cobra Stripe',
                'value' => $payment['value'],
                'hint' => $payment['hint'],
                'icon' => 'ti-credit-card',
                'tone' => 'primary',
            ],
        ];
    }

    /**
     * @param  Collection<int, ServiceSync>  $subscriptions
     * @param  array{brand: string, last4: string, exp_month: int, exp_year: int}|null  $paymentCard
     * @return array{value: string, hint: string}
     */
    private static function paymentCard(Collection $subscriptions, ?array $paymentCard): array
    {
        $active = $subscriptions->filter(
            fn (ServiceSync $sync): bool => strtolower(trim((string) $sync->status)) === 'active',
        );
        $source = $active->isNotEmpty() ? $active : $subscriptions;
        $label = $source
            ->map(fn (ServiceSync $sync): string => $sync->clientPaymentMethodLabel())
            ->first(fn (string $label): bool => $label !== '') ?? '';

        $card = $paymentCard ?? self::cardFromSubscriptions($source);
        $paysByCard = $card !== null && ($label === '' || $label === 'Cargo automático' || str_contains($label, '····'));
        if ($paysByCard)
        {
            $brand = trim((string) ($card['brand'] ?? ''));
            $last4 = trim((string) ($card['last4'] ?? ''));
            $value = $brand !== '' ? ucfirst($brand).' ···· '.$last4 : '···· '.$last4;
            $expiry = self::expiryLabel($card);

            return [
                'value' => $value,
                'hint' => $expiry !== '' ? $expiry : 'Según las suscripciones',
            ];
        }

        return [
            'value' => $label !== '' ? $label : '—',
            'hint' => 'Según las suscripciones',
        ];
    }

    /**
     * @param  Collection<int, ServiceSync>  $subscriptions
     * @return array{brand: string, last4: string, exp_month: int, exp_year: int}|null
     */
    private static function cardFromSubscriptions(Collection $subscriptions): ?array
    {
        foreach ($subscriptions as $sync)
        {
            $card = data_get($sync->raw_payload, 'default_payment_method.card');
            if (! is_array($card) || trim((string) ($card['last4'] ?? '')) === '')
            {
                continue;
            }

            return [
                'brand' => trim((string) ($card['brand'] ?? '')),
                'last4' => trim((string) $card['last4']),
                'exp_month' => (int) ($card['exp_month'] ?? 0),
                'exp_year' => (int) ($card['exp_year'] ?? 0),
            ];
        }

        return null;
    }

    /**
     * @param  array{brand?: string, last4?: string, exp_month?: int, exp_year?: int}  $card
     */
    private static function expiryLabel(array $card): string
    {
        $month = (int) ($card['exp_month'] ?? 0);
        $year = (int) ($card['exp_year'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 1)
        {
            return '';
        }

        return sprintf('Vence %02d/%d', $month, $year);
    }

    /**
     * @param  array<string, float>  $sums
     */
    private static function money(array $sums): string
    {
        $sums = array_filter($sums, fn (float $amount): bool => $amount > 0);
        if ($sums === [])
        {
            return '0 €';
        }

        $euros = StripeInvoiceMetrics::sumAmountsConvertedToCurrency($sums, 'EUR');
        if ($euros === null)
        {
            $euros = (float) ($sums['EUR'] ?? 0);
        }

        return number_format($euros, 2).' €';
    }

    private static function isSell(Invoice $invoice): bool
    {
        $operation = strtolower(trim((string) ($invoice->operation ?? 'sell')));

        return $operation === '' || $operation === 'sell';
    }

    private static function isCreditNote(Invoice $invoice): bool
    {
        return (int) $invoice->type_id === 2
            || str_starts_with((string) $invoice->source_reference_id, 'cn_');
    }
}
