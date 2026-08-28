<?php

namespace App\Services\Billing;

use App\Models\InvoiceSync;
use App\Support\DatabaseSequence;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class StripeInvoiceSyncUpserter
{
    public function upsertFromPayload(int $teamId, array $invoicePayload): ?InvoiceSync
    {
        $externalId = trim((string) Arr::get($invoicePayload, 'id'));
        if ($externalId === '')
        {
            return null;
        }

        $customerData = [];
        $customerField = Arr::get($invoicePayload, 'customer');
        if (is_array($customerField))
        {
            $customerData = $customerField;
        }

        $customerId = is_string($customerField)
            ? $customerField
            : Arr::get($customerData, 'id');

        $subscriptionField = Arr::get($invoicePayload, 'subscription');
        $subscriptionId = is_string($subscriptionField)
            ? $subscriptionField
            : Arr::get($subscriptionField, 'id');

        $discountLabels = [];
        $discounts = Arr::get($invoicePayload, 'discounts', []);
        if (is_array($discounts))
        {
            foreach ($discounts as $discount)
            {
                $name = Arr::get($discount, 'coupon.name')
                    ?? Arr::get($discount, 'coupon.id')
                    ?? Arr::get($discount, 'promotion_code.code');

                if (filled($name))
                {
                    $discountLabels[] = (string) $name;
                }
            }
        }

        return DatabaseSequence::retryOnDuplicateId('invoice_syncs', function () use ($teamId, $externalId, $subscriptionId, $customerId, $customerData, $invoicePayload, $discountLabels)
        {
            return InvoiceSync::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'provider' => 'stripe',
                    'external_id' => $externalId,
                ],
                [
                    'stripe_subscription_id' => $subscriptionId,
                    'customer_id' => $customerId,
                    'customer_email' => Arr::get($invoicePayload, 'customer_email')
                        ?? Arr::get($invoicePayload, 'customer_details.email')
                        ?? Arr::get($customerData, 'email'),
                    'customer_name' => Arr::get($invoicePayload, 'customer_name')
                        ?? Arr::get($invoicePayload, 'customer_details.name')
                        ?? Arr::get($customerData, 'name'),
                    'customer_description' => Arr::get($customerData, 'description'),
                    'customer_tax_id' => Arr::get($invoicePayload, 'customer_tax_ids.0.value')
                        ?? Arr::get($invoicePayload, 'customer_details.tax_ids.0.value'),
                    'customer_address_country' => strtoupper((string) (Arr::get($invoicePayload, 'customer_address.country')
                        ?? Arr::get($invoicePayload, 'customer_details.address.country')
                        ?? Arr::get($customerData, 'address.country'))) ?: null,
                    'number' => Arr::get($invoicePayload, 'number'),
                    'status' => Arr::get($invoicePayload, 'status'),
                    'billing_reason' => Arr::get($invoicePayload, 'billing_reason'),
                    'closed' => (bool) Arr::get($invoicePayload, 'closed', false),
                    'currency' => strtolower((string) Arr::get($invoicePayload, 'currency', 'usd')),
                    'amount_due' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'amount_due_decimal'),
                        Arr::get($invoicePayload, 'amount_due'),
                    ),
                    'amount_paid' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'amount_paid_decimal'),
                        Arr::get($invoicePayload, 'amount_paid'),
                    ),
                    'amount_remaining' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'amount_remaining_decimal'),
                        Arr::get($invoicePayload, 'amount_remaining'),
                    ),
                    'subtotal' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'subtotal_excluding_tax_decimal')
                        ?? Arr::get($invoicePayload, 'subtotal_decimal'),
                        Arr::get($invoicePayload, 'subtotal_excluding_tax')
                        ?? Arr::get($invoicePayload, 'subtotal'),
                    ),
                    'tax' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'tax_decimal'),
                        Arr::get($invoicePayload, 'tax'),
                    ),
                    'total' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'total_decimal'),
                        Arr::get($invoicePayload, 'total'),
                    ),
                    'total_discount_amount' => $this->normalizeAmount(
                        Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax_decimal')
                        ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount_decimal'),
                        Arr::get($invoicePayload, 'total_discount_amounts.0.amount_excluding_tax')
                        ?? Arr::get($invoicePayload, 'total_discount_amounts.0.amount'),
                    ),
                    'applied_coupons' => $discountLabels === [] ? null : implode(', ', $discountLabels),
                    'invoice_created_at' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'created')),
                    'invoice_due_date' => $this->normalizeTimestamp(Arr::get($invoicePayload, 'due_date')),
                    'paid' => (bool) Arr::get($invoicePayload, 'paid', false),
                    'hosted_invoice_url' => Arr::get($invoicePayload, 'hosted_invoice_url'),
                    'invoice_pdf' => Arr::get($invoicePayload, 'invoice_pdf')
                        ?? Arr::get($invoicePayload, 'pdf')
                        ?? Arr::get($invoicePayload, 'pdf_url'),
                    'last_synced_at' => now(),
                    'raw_payload' => $invoicePayload,
                ],
            );
        });
    }

    private function normalizeAmount(?string $decimalAmount, mixed $integerAmount): ?float
    {
        if ($decimalAmount !== null)
        {
            return (float) $decimalAmount;
        }

        if (is_numeric($integerAmount))
        {
            return ((float) $integerAmount) / 100;
        }

        return null;
    }

    private function normalizeTimestamp(mixed $value): ?Carbon
    {
        if (! is_numeric($value))
        {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $value)->setTimezone(config('app.timezone'));
    }
}
