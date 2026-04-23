<?php

namespace App\Actions\Subscriptions;

use App\Models\InvoiceSync;
use App\Models\StripeSubscription;
use App\Models\SubscriptionChange;
use App\Models\Team;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncStripeSubscriptions
{
    public function __construct(
        private readonly StripeSubscriptionService $stripe,
    ) {}

    /**
     * @param  \App\Models\Team|null  $syncingTeam  Team that runs the sync (Stripe secret + Humano scope). When set, all rows get this team_id; link to enterprise is via customer_id = enterprises.code (no extra column).
     */
    public function handle(?Team $syncingTeam = null): int
    {
        $processed = 0;

        foreach ($this->stripe->subscriptions() as $stripeSubscription)
        {
            $payload = $stripeSubscription->toArray();
            $mapped = $this->mapSubscription($payload);

            if ($syncingTeam)
            {
                $mapped['team_id'] = $syncingTeam->id;
            } elseif (filled($mapped['customer_id'] ?? null))
            {
                $mapped['team_id'] = Team::where('stripe_id', $mapped['customer_id'])->value('id');
            } else
            {
                $mapped['team_id'] = null;
            }

            $note = $mapped['amount_for_note'] !== null
                ? $this->buildInvoiceNote($mapped['amount_for_note'], $mapped['price_currency'])
                : null;
            $mapped['invoice_note'] = $note;
            unset($mapped['amount_for_note']);

            $subscription = StripeSubscription::firstWhere('stripe_id', $mapped['stripe_id']);

            if ($subscription)
            {
                // PROTECTION: Do not update subscriptions of type 'buy' (they are manual)
                if ($subscription->type === 'buy')
                {
                    continue;
                }
                $this->updateSubscription($subscription, $mapped);
            } else
            {
                $subscription = StripeSubscription::create($mapped + ['last_synced_at' => now()]);

                SubscriptionChange::create([
                    'subscription_id' => $subscription->id,
                    'source' => 'stripe',
                    'changed_fields' => array_keys($mapped),
                    'previous_values' => null,
                    'current_values' => Arr::only($subscription->toArray(), array_keys($mapped)),
                    'detected_at' => now(),
                ]);
            }

            $this->syncLatestInvoiceFromSubscription(
                $payload,
                $mapped['team_id'] ?? null,
            );

            $processed++;
        }

        return $processed;
    }

    private function updateSubscription(StripeSubscription $subscription, array $payload): void
    {
        // Preserve local metadata that doesn't come from Stripe (plan, whm_status, etc.)
        if (! empty($subscription->data) && isset($payload['data']))
        {
            // Merge: Stripe metadata updates, but we preserve local fields that are not in Stripe
            $localData = $subscription->data;
            $stripeData = $payload['data'] ?? [];

            // Fields that should only come from local sources (not from Stripe)
            $localOnlyFields = ['plan', 'whm_status', 'disk_used', 'disk_limit', 'dns_has_own_ns',
                'dns_domain_points_to_service', 'dns_mail_points_to_service',
                'dns_has_spf_include', 'dns_current_ns', 'dns_current_ips', 'dns_last_check'];

            // Preserve local fields that are not in Stripe
            foreach ($localOnlyFields as $field)
            {
                if (isset($localData[$field]) && ! isset($stripeData[$field]))
                {
                    $stripeData[$field] = $localData[$field];
                }
            }

            $payload['data'] = $stripeData;
        }

        $subscription->fill($payload + ['last_synced_at' => now()]);
        $dirty = $subscription->getDirty();

        if (empty($dirty))
        {
            return;
        }

        $original = Arr::only($subscription->getOriginal(), array_keys($dirty));

        $subscription->save();

        SubscriptionChange::create([
            'subscription_id' => $subscription->id,
            'source' => 'stripe',
            'changed_fields' => array_keys($dirty),
            'previous_values' => $original,
            'current_values' => Arr::only($subscription->fresh()->toArray(), array_keys($dirty)),
            'detected_at' => now(),
        ]);
    }

    private function mapSubscription(array $payload): array
    {
        $item = Arr::get($payload, 'items.data.0', []);
        $price = Arr::get($item, 'price', []);
        $customer = Arr::get($payload, 'customer');
        $customerArray = is_array($customer) ? $customer : [];

        $unitAmount = $this->normalizeAmount(
            Arr::get($price, 'unit_amount_decimal'),
            Arr::get($price, 'unit_amount'),
        );

        $quantity = (int) (Arr::get($item, 'quantity', 1) ?: 1);

        $amountSubtotal = $unitAmount !== null ? $unitAmount * $quantity : null;
        $amountTotal = $this->normalizeAmount(
            Arr::get($payload, 'latest_invoice.amount_due_decimal'),
            Arr::get($payload, 'latest_invoice.amount_due'),
        ) ?? $amountSubtotal;

        $priceCurrency = strtoupper(Arr::get($price, 'currency', 'USD'));

        $amountForNote = $amountTotal ?? $amountSubtotal ?? $unitAmount;

        // Convert amounts to USD, ARS, EUR using ExchangeRate model
        $convertedAmounts = [];
        if ($amountForNote !== null && class_exists(\App\Models\ExchangeRate::class))
        {
            $convertedAmounts['USD'] = $priceCurrency === 'USD' ? $amountForNote : \App\Models\ExchangeRate::convert($amountForNote, $priceCurrency, 'USD');
            $convertedAmounts['ARS'] = \App\Models\ExchangeRate::convert($amountForNote, $priceCurrency, 'ARS');
            $convertedAmounts['EUR'] = \App\Models\ExchangeRate::convert($amountForNote, $priceCurrency, 'EUR');
        } else
        {
            // Fallback: only set USD if currency is USD
            if ($priceCurrency === 'USD')
            {
                $convertedAmounts['USD'] = $amountForNote;
            }
        }

        $country = $this->resolveCountry($payload, $customerArray);
        $taxData = $this->resolveTaxData($payload);
        $metadata = Arr::get($payload, 'metadata', []);

        // Filter metadata to only include predefined fields
        // Support both 'category' (new) and 'type' (legacy) for reading, but save as 'category'
        $category = Arr::get($metadata, 'category') ?? Arr::get($metadata, 'type');

        $filteredMetadata = array_filter([
            'category' => $category,
            'server' => Arr::get($metadata, 'server'),
            'domain' => Arr::get($metadata, 'domain'),
            'user' => Arr::get($metadata, 'user'),
            'email' => Arr::get($metadata, 'email'),
            'auto_suspend' => Arr::get($metadata, 'auto_suspend'),
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'stripe_id' => Arr::get($payload, 'id'),
            'type' => 'sell',
            'customer_id' => is_string($customer)
                ? $customer
                : Arr::get($customerArray, 'id'),
            'customer_email' => Arr::get($payload, 'customer_email')
                ?? Arr::get($payload, 'customer_details.email')
                ?? Arr::get($customerArray, 'email'),
            'customer_name' => Arr::get($payload, 'customer_name')
                ?? Arr::get($payload, 'customer_details.name')
                ?? Arr::get($customerArray, 'name'),
            'customer_country' => $country,
            'customer_tax_id_type' => Arr::get($taxData ?? [], 'type'),
            'customer_tax_id' => Arr::get($taxData ?? [], 'value'),
            'status' => Arr::get($payload, 'status'),
            'collection_method' => Arr::get($payload, 'collection_method'),
            'plan_name' => Arr::get($price, 'nickname')
                ?? Arr::get($price, 'product.name')
                ?? Arr::get($item, 'plan.nickname')
                ?? Arr::get($payload, 'description'),
            'plan_interval' => Arr::get($price, 'recurring.interval'),
            'plan_interval_count' => Arr::get($price, 'recurring.interval_count'),
            'quantity' => $quantity,
            'price_currency' => $priceCurrency,
            'unit_amount' => $unitAmount,
            'amount_subtotal' => $amountSubtotal,
            'amount_total' => $amountTotal,
            'amount_usd' => $convertedAmounts['USD'] ?? ($priceCurrency === 'USD' ? $amountForNote : null),
            'amount_ars' => $convertedAmounts['ARS'] ?? null,
            'amount_eur' => $convertedAmounts['EUR'] ?? null,
            'current_period_start' => $this->resolveCurrentPeriodStart($payload, $item),
            'current_period_end' => $this->resolveCurrentPeriodEnd($payload, $item),
            'cancel_at_period_end' => (bool) Arr::get($payload, 'cancel_at_period_end', false),
            'canceled_at' => $this->normalizeTimestamp(Arr::get($payload, 'canceled_at')),
            'raw_payload' => $payload,
            'amount_for_note' => $amountForNote,
            'data' => ! empty($filteredMetadata) ? $filteredMetadata : null,
        ];
    }

    private function normalizeAmount(?string $decimalAmount, ?int $integerAmount): ?float
    {
        if ($decimalAmount !== null)
        {
            return (float) $decimalAmount;
        }

        if ($integerAmount !== null)
        {
            return $integerAmount / 100;
        }

        return null;
    }

    private function normalizeTimestamp(?int $value): ?Carbon
    {
        if (empty($value))
        {
            return null;
        }

        return Carbon::createFromTimestampUTC($value)->setTimezone(config('app.timezone'));
    }

    private function resolveCountry(array $payload, array $customer): ?string
    {
        return strtoupper(
            Arr::get($payload, 'customer_details.address.country')
            ?? Arr::get($payload, 'latest_invoice.customer_address.country')
            ?? Arr::get($customer, 'address.country')
            ?? Arr::get($payload, 'customer_address.country'),
        ) ?: null;
    }

    private function resolveTaxData(array $payload): ?array
    {
        $taxIds = Arr::get($payload, 'customer_details.tax_ids', []);

        if (! is_array($taxIds) || empty($taxIds))
        {
            return null;
        }

        return collect($taxIds)
            ->filter(fn ($tax) => filled(Arr::get($tax, 'value')))
            ->first();
    }

    private function resolveCurrentPeriodStart(array $payload, array $item): ?Carbon
    {
        // Try multiple locations where Stripe might store current_period_start
        $timestamp = Arr::get($payload, 'current_period_start')
            ?? Arr::get($item, 'current_period_start')
            ?? Arr::get($payload, 'billing_cycle_anchor');

        return $this->normalizeTimestamp($timestamp);
    }

    private function resolveCurrentPeriodEnd(array $payload, array $item): ?Carbon
    {
        // Try multiple locations where Stripe might store current_period_end
        $timestamp = Arr::get($payload, 'current_period_end')
            ?? Arr::get($item, 'current_period_end');

        return $this->normalizeTimestamp($timestamp);
    }

    private function buildInvoiceNote(?float $amount, string $currency): ?string
    {
        if ($amount === null)
        {
            return null;
        }

        $formattedAmount = number_format($amount, 2, '.', ',');

        return "{$currency} {$formattedAmount}";
    }

    private function syncLatestInvoiceFromSubscription(array $subscriptionPayload, ?int $teamId): void
    {
        if (! $teamId)
        {
            return;
        }

        $invoicePayload = Arr::get($subscriptionPayload, 'latest_invoice');
        if (is_string($invoicePayload) || ! is_array($invoicePayload))
        {
            return;
        }

        $externalId = trim((string) Arr::get($invoicePayload, 'id'));
        if ($externalId === '')
        {
            return;
        }

        $customerFromInvoice = Arr::get($invoicePayload, 'customer');
        $customerFromSubscription = Arr::get($subscriptionPayload, 'customer');
        $customerData = [];
        if (is_array($customerFromInvoice))
        {
            $customerData = $customerFromInvoice;
        } elseif (is_array($customerFromSubscription))
        {
            $customerData = $customerFromSubscription;
        }

        $customerId = is_string($customerFromInvoice)
            ? $customerFromInvoice
            : (is_string($customerFromSubscription) ? $customerFromSubscription : Arr::get($customerData, 'id'));

        $discounts = Arr::get($invoicePayload, 'discounts', []);
        $discountLabels = [];
        if (is_array($discounts))
        {
            foreach ($discounts as $discount)
            {
                $name = Arr::get($discount, 'coupon.name')
                    ?? Arr::get($discount, 'coupon.id')
                    ?? Arr::get($discount, 'promotion_code.code');

                if (filled($name))
                {
                    $discountLabels[] = $name;
                }
            }
        }

        InvoiceSync::updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => 'stripe',
                'external_id' => $externalId,
            ],
            [
                'stripe_subscription_id' => Arr::get($subscriptionPayload, 'id'),
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
                'invoice_pdf' => Arr::get($invoicePayload, 'invoice_pdf'),
                'last_synced_at' => now(),
                'raw_payload' => $invoicePayload,
            ],
        );
    }
}
