<?php

namespace App\Services\Billing;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\PaymentSync;
use App\Support\AiTasks;
use App\Support\MercadoPagoPaidInvoiceLinker;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class MercadoPagoAutoAssignMatcherService
{
    private const AMOUNT_TOLERANCE = 0.05;

    private const AI_INSTRUCTIONS = <<<'PROMPT'
You match Mercado Pago bank-transfer payments to Stripe invoices already paid (or open) in Humano.
Given one payment and candidate invoices (same amount), pick the single best invoice_id.
Prefer closest paid_at / invoice date to the payment date. Consider description and enterprise name only as weak signals.
Return ONLY valid JSON:
{"invoice_id": 123, "reason": "short explanation in Spanish"}
PROMPT;

    /**
     * Build unique payment→invoice suggestions for the team pending MP queue.
     *
     * @return list<array{
     *     sync_id: int,
     *     enterprise_id: int,
     *     enterprise_name: string,
     *     invoice_ids: list<int>,
     *     invoice_numbers: list<string>,
     *     kind: string,
     *     confidence: float,
     *     reason: string,
     *     amount: float,
     *     currency: string,
     *     payment_date: string|null,
     *     external_id: string,
     *     identification_code: string|null
     * }>
     */
    public function buildSuggestions(int $teamId, int $limit = 25, bool $useAi = false): array
    {
        $syncs = $this->pendingSyncs($teamId, $limit);
        if ($syncs->isEmpty())
        {
            return [];
        }

        $enterprises = Enterprise::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('type_id', 1)
            ->get(['id', 'name', 'code', 'email'])
            ->keyBy('id');

        $enterprisesByCode = [];
        $enterprisesByEmail = [];
        $enterprisesByNormalizedName = [];
        foreach ($enterprises as $enterprise)
        {
            $code = trim((string) ($enterprise->code ?? ''));
            if ($code !== '')
            {
                $enterprisesByCode[$code] = (int) $enterprise->id;
            }

            $email = strtolower(trim((string) ($enterprise->email ?? '')));
            if ($email !== '')
            {
                $enterprisesByEmail[$email][] = (int) $enterprise->id;
            }

            $normalizedName = $this->normalizePersonName((string) $enterprise->name);
            if ($normalizedName !== '')
            {
                $enterprisesByNormalizedName[$normalizedName][] = (int) $enterprise->id;
            }
        }

        $amounts = $syncs
            ->map(fn (PaymentSync $sync) => $this->amountMajor($sync))
            ->filter(fn (float $amount) => $amount > 0)
            ->unique()
            ->values()
            ->all();

        $paidPool = $this->loadPaidUnlinkedCandidates($teamId, $amounts);
        $openPool = $this->loadOpenCandidates($teamId, $amounts);

        $usedInvoiceIds = [];
        $suggestions = [];
        $aiBudget = $useAi ? 3 : 0;

        foreach ($syncs as $sync)
        {
            $suggestion = $this->suggestForSync(
                $sync,
                $enterprises,
                $enterprisesByCode,
                $enterprisesByEmail,
                $enterprisesByNormalizedName,
                $paidPool,
                $openPool,
                $usedInvoiceIds,
                $aiBudget,
            );

            if ($suggestion === null)
            {
                continue;
            }

            if (($suggestion['used_ai'] ?? false) === true)
            {
                $aiBudget = max(0, $aiBudget - 1);
            }

            foreach ($suggestion['invoice_ids'] as $invoiceId)
            {
                $usedInvoiceIds[$invoiceId] = true;
            }

            unset($suggestion['used_ai']);
            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * @param  Collection<int, Enterprise>  $enterprises
     * @param  array<string, int>  $enterprisesByCode
     * @param  array<string, list<int>>  $enterprisesByEmail
     * @param  array<string, list<int>>  $enterprisesByNormalizedName
     * @param  Collection<int, Invoice>  $paidPool
     * @param  Collection<int, Invoice>  $openPool
     * @param  array<int, true>  $usedInvoiceIds
     * @return array{
     *     sync_id: int,
     *     enterprise_id: int,
     *     enterprise_name: string,
     *     invoice_ids: list<int>,
     *     invoice_numbers: list<string>,
     *     kind: string,
     *     confidence: float,
     *     reason: string,
     *     amount: float,
     *     currency: string,
     *     payment_date: string|null,
     *     external_id: string,
     *     identification_code: string|null,
     *     settlement_payer_name: string|null,
     *     settlement_payer_id_number: string|null,
     *     used_ai?: bool
     * }|null
     */
    private function suggestForSync(
        PaymentSync $sync,
        Collection $enterprises,
        array $enterprisesByCode,
        array $enterprisesByEmail,
        array $enterprisesByNormalizedName,
        Collection $paidPool,
        Collection $openPool,
        array $usedInvoiceIds,
        int $aiBudget,
    ): ?array {
        $amount = $this->amountMajor($sync);
        if ($amount <= 0)
        {
            return null;
        }

        [$enterpriseId, $enterpriseSource] = $this->resolveEnterpriseId(
            $sync,
            $enterprises,
            $enterprisesByCode,
            $enterprisesByEmail,
            $enterprisesByNormalizedName,
        );

        $paidCandidates = $paidPool
            ->reject(fn (Invoice $invoice) => isset($usedInvoiceIds[(int) $invoice->id]))
            ->filter(fn (Invoice $invoice) => $this->amountsMatch((float) $invoice->total_amount, $amount))
            ->when(
                $enterpriseId !== null,
                fn (Collection $collection) => $collection->where('enterprise_id', $enterpriseId),
            )
            ->values();

        if ($enterpriseId === null)
        {
            $enterpriseIds = $paidCandidates->pluck('enterprise_id')->unique()->values();
            if ($enterpriseIds->count() === 1)
            {
                $enterpriseId = (int) $enterpriseIds->first();
                $enterpriseSource = 'amount';
                $paidCandidates = $paidCandidates->where('enterprise_id', $enterpriseId)->values();
            } elseif ($enterpriseIds->count() > 1)
            {
                // Amount is shared by several paid customers — do not guess via open invoices.
                return null;
            }
        }

        /** @var Enterprise|null $enterprise */
        $enterprise = $enterpriseId !== null ? $enterprises->get($enterpriseId) : null;

        if ($enterprise && $paidCandidates->isNotEmpty())
        {
            $picked = $this->pickBestInvoice($sync, $paidCandidates, $aiBudget > 0, preferPaidAt: true);
            if ($picked !== null)
            {
                $suggestion = $this->formatSuggestion(
                    $sync,
                    $enterprise,
                    [$picked['invoice']],
                    'paid_link',
                    $picked['confidence'],
                    $picked['reason'],
                    $enterpriseSource,
                    $amount,
                );
                $suggestion['used_ai'] = $picked['used_ai'] ?? false;

                return $suggestion;
            }
        }

        // Without a resolved client, open invoices of a popular plan amount are unsafe.
        if ($enterpriseId === null)
        {
            return null;
        }

        $openCandidates = $openPool
            ->reject(fn (Invoice $invoice) => isset($usedInvoiceIds[(int) $invoice->id]))
            ->filter(fn (Invoice $invoice) => $this->amountsMatch((float) $invoice->balance, $amount))
            ->where('enterprise_id', $enterpriseId)
            ->values();

        if (! $enterprise || $openCandidates->isEmpty())
        {
            return null;
        }

        $picked = $this->pickBestInvoice($sync, $openCandidates, $aiBudget > 0, preferPaidAt: false);
        if ($picked === null)
        {
            return null;
        }

        $suggestion = $this->formatSuggestion(
            $sync,
            $enterprise,
            [$picked['invoice']],
            'exact',
            $picked['confidence'],
            $picked['reason'],
            $enterpriseSource,
            $amount,
        );
        $suggestion['used_ai'] = $picked['used_ai'] ?? false;

        return $suggestion;
    }

    /**
     * @param  list<float>  $amounts
     * @return Collection<int, Invoice>
     */
    private function loadPaidUnlinkedCandidates(int $teamId, array $amounts): Collection
    {
        if ($amounts === [])
        {
            return collect();
        }

        $min = min($amounts) - self::AMOUNT_TOLERANCE;
        $max = max($amounts) + self::AMOUNT_TOLERANCE;

        $invoices = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('operation', 'sell')
            ->where('source_provider', 'stripe')
            ->where('balance', '<=', 0)
            ->where('source_reference_id', 'like', 'in_%')
            ->whereBetween('total_amount', [$min, $max])
            ->whereExists(function ($query): void
            {
                $query->selectRaw('1')
                    ->from('invoice_syncs')
                    ->whereColumn('invoice_syncs.team_id', 'invoices.team_id')
                    ->whereColumn('invoice_syncs.external_id', 'invoices.source_reference_id')
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($paid): void
                    {
                        $paid->where('invoice_syncs.paid', true)
                            ->orWhere('invoice_syncs.status', 'paid');
                    })
                    ->whereRaw("(
                        NULLIF(TRIM(COALESCE(
                            invoice_syncs.raw_payload->'metadata'->>'mercadopago_id',
                            invoice_syncs.raw_payload->'metadata'->>'mercadopago_payment_id',
                            ''
                        )), '') IS NULL
                    )");
            })
            ->whereNotExists(function ($query): void
            {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.invoice_id', 'invoices.id')
                    ->where('payments.source_provider', 'mercadopago')
                    ->where('payments.status', '!=', 0);
            })
            ->with(['enterprise:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        if ($invoices->isEmpty())
        {
            return $invoices;
        }

        $syncsByExternalId = InvoiceSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'stripe')
            ->whereIn('external_id', $invoices->pluck('source_reference_id')->all())
            ->orderByDesc('id')
            ->get()
            ->unique('external_id')
            ->keyBy('external_id');

        $invoices->each(function (Invoice $invoice) use ($syncsByExternalId): void
        {
            $invoice->setRelation('stripeInvoiceSync', $syncsByExternalId->get((string) $invoice->source_reference_id));
        });

        return $invoices->values();
    }

    /**
     * @param  list<float>  $amounts
     * @return Collection<int, Invoice>
     */
    private function loadOpenCandidates(int $teamId, array $amounts): Collection
    {
        if ($amounts === [])
        {
            return collect();
        }

        $min = min($amounts) - self::AMOUNT_TOLERANCE;
        $max = max($amounts) + self::AMOUNT_TOLERANCE;

        return Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('operation', 'sell')
            ->where('balance', '>', 0)
            ->whereBetween('balance', [$min, $max])
            ->with(['enterprise:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->values();
    }

    /**
     * @param  Collection<int, Invoice>  $candidates
     * @return array{invoice: Invoice, confidence: float, reason: string, used_ai: bool}|null
     */
    private function pickBestInvoice(
        PaymentSync $sync,
        Collection $candidates,
        bool $useAi,
        bool $preferPaidAt,
    ): ?array {
        if ($candidates->isEmpty())
        {
            return null;
        }

        if ($candidates->count() === 1)
        {
            /** @var Invoice $invoice */
            $invoice = $candidates->first();

            return [
                'invoice' => $invoice,
                'confidence' => 0.92,
                'reason' => __('payment_sync.mercadopago.auto_assign.reason_unique_amount'),
                'used_ai' => false,
            ];
        }

        $ranked = $candidates
            ->map(function (Invoice $invoice) use ($sync, $preferPaidAt): array
            {
                $reference = $preferPaidAt
                    ? (MercadoPagoPaidInvoiceLinker::stripePaidAt($invoice) ?? $this->invoiceDate($invoice))
                    : $this->invoiceDate($invoice);

                $paymentAt = $sync->charge_created_at;
                $distance = ($reference instanceof CarbonInterface && $paymentAt instanceof CarbonInterface)
                    ? abs($reference->getTimestamp() - $paymentAt->getTimestamp())
                    : PHP_INT_MAX;

                return [
                    'invoice' => $invoice,
                    'distance' => $distance,
                ];
            })
            ->sortBy('distance')
            ->values();

        $best = $ranked->first();
        $second = $ranked->get(1);
        $bestDistance = (int) $best['distance'];
        $secondDistance = $second ? (int) $second['distance'] : PHP_INT_MAX;
        $ambiguous = $second !== null && abs($bestDistance - $secondDistance) <= 3 * 86400;

        if ($useAi && $ambiguous)
        {
            $aiPick = $this->resolveAmbiguousWithAi($sync, $ranked->take(6)->pluck('invoice')->values());
            if ($aiPick !== null)
            {
                $aiPick['used_ai'] = true;

                return $aiPick;
            }
        }

        /** @var Invoice $invoice */
        $invoice = $best['invoice'];
        $days = $bestDistance === PHP_INT_MAX ? null : (int) round($bestDistance / 86400);

        return [
            'invoice' => $invoice,
            'confidence' => $ambiguous ? 0.62 : 0.84,
            'reason' => $days === null
                ? __('payment_sync.mercadopago.auto_assign.reason_amount_date_unknown')
                : __('payment_sync.mercadopago.auto_assign.reason_closest_date', ['days' => $days]),
            'used_ai' => false,
        ];
    }

    /**
     * @param  Collection<int, Invoice>  $candidates
     * @return array{invoice: Invoice, confidence: float, reason: string}|null
     */
    private function resolveAmbiguousWithAi(PaymentSync $sync, Collection $candidates): ?array
    {
        try
        {
            $payload = [
                'payment' => [
                    'external_id' => (string) $sync->external_id,
                    'amount' => $this->amountMajor($sync),
                    'currency' => strtoupper((string) $sync->currency),
                    'date' => $sync->charge_created_at?->toIso8601String(),
                    'description' => (string) ($sync->description ?? ''),
                    'payer_email' => (string) ($sync->customer_email ?? ''),
                ],
                'candidates' => $candidates->map(function (Invoice $invoice): array
                {
                    return [
                        'invoice_id' => (int) $invoice->id,
                        'number' => (string) ($invoice->number ?? ''),
                        'total' => (float) $invoice->total_amount,
                        'balance' => (float) $invoice->balance,
                        'invoice_date' => $this->invoiceDate($invoice)?->toDateString(),
                        'paid_at' => MercadoPagoPaidInvoiceLinker::stripePaidAt($invoice)?->toIso8601String(),
                        'enterprise' => (string) ($invoice->enterprise?->name ?? ''),
                    ];
                })->values()->all(),
            ];

            $response = agent(
                instructions: self::AI_INSTRUCTIONS,
                messages: [],
                tools: [],
            )->prompt(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                [],
                provider: AiTasks::provider('summary'),
            );

            $decoded = json_decode((string) ($response->text ?? ''), true);
            if (! is_array($decoded) && preg_match('/\{.*\}/s', (string) ($response->text ?? ''), $matches) === 1)
            {
                $decoded = json_decode($matches[0], true);
            }

            if (! is_array($decoded))
            {
                return null;
            }

            $invoiceId = (int) ($decoded['invoice_id'] ?? 0);
            /** @var Invoice|null $invoice */
            $invoice = $candidates->first(fn (Invoice $candidate) => (int) $candidate->id === $invoiceId);
            if (! $invoice)
            {
                return null;
            }

            $reason = trim((string) ($decoded['reason'] ?? ''));
            if ($reason === '')
            {
                $reason = __('payment_sync.mercadopago.auto_assign.reason_ai');
            }

            return [
                'invoice' => $invoice,
                'confidence' => 0.78,
                'reason' => $reason,
            ];
        } catch (\Throwable $e)
        {
            Log::warning('MercadoPago auto-assign AI disambiguation failed', [
                'sync_id' => $sync->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<Invoice>  $invoices
     * @return array{
     *     sync_id: int,
     *     enterprise_id: int,
     *     enterprise_name: string,
     *     invoice_ids: list<int>,
     *     invoice_numbers: list<string>,
     *     kind: string,
     *     confidence: float,
     *     reason: string,
     *     amount: float,
     *     currency: string,
     *     payment_date: string|null,
     *     external_id: string,
     *     identification_code: string|null
     * }
     */
    private function formatSuggestion(
        PaymentSync $sync,
        Enterprise $enterprise,
        array $invoices,
        string $kind,
        float $confidence,
        string $reason,
        string $enterpriseSource,
        float $amount,
    ): array {
        $sourceHint = match ($enterpriseSource)
        {
            'code' => __('payment_sync.mercadopago.auto_assign.client_by_code'),
            'email' => __('payment_sync.mercadopago.auto_assign.client_by_email'),
            'settlement_name' => __('payment_sync.mercadopago.auto_assign.client_by_settlement_name'),
            'amount' => __('payment_sync.mercadopago.auto_assign.client_by_amount'),
            default => '',
        };

        return [
            'sync_id' => (int) $sync->id,
            'enterprise_id' => (int) $enterprise->id,
            'enterprise_name' => (string) $enterprise->name,
            'invoice_ids' => array_map(fn (Invoice $invoice) => (int) $invoice->id, $invoices),
            'invoice_numbers' => array_map(
                fn (Invoice $invoice) => (string) ($invoice->number ?: '#'.$invoice->id),
                $invoices,
            ),
            'kind' => $kind,
            'confidence' => $confidence,
            'reason' => trim($reason.($sourceHint !== '' ? ' '.$sourceHint : '')),
            'amount' => $amount,
            'currency' => strtoupper((string) $sync->currency),
            'payment_date' => $sync->charge_created_at?->format('d/m/Y H:i'),
            'external_id' => (string) $sync->external_id,
            'identification_code' => $sync->identificationCode(),
            'settlement_payer_name' => $sync->displayPayerName(),
            'settlement_payer_id_number' => $sync->settlementPayerIdNumber(),
        ];
    }

    /**
     * @return Collection<int, PaymentSync>
     */
    private function pendingSyncs(int $teamId, int $limit): Collection
    {
        return PaymentSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->where('status', 'approved')
            ->whereNotExists(function ($sub): void
            {
                $sub->from('payments')
                    ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                    ->where('payments.source_provider', 'mercadopago')
                    ->where(function ($inner): void
                    {
                        $inner->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                            ->orWhereRaw("payments.source_reference_id LIKE payment_syncs.external_id || ':%'");
                    });
            })
            ->whereNotExists(function ($sub) use ($teamId): void
            {
                $sub->selectRaw('1')
                    ->from('invoice_syncs')
                    ->where('invoice_syncs.team_id', $teamId)
                    ->where('invoice_syncs.provider', 'stripe')
                    ->where(function ($linked): void
                    {
                        $linked->whereRaw("NULLIF(TRIM(COALESCE(invoice_syncs.raw_payload->'metadata'->>'mercadopago_id','')), '') = payment_syncs.external_id")
                            ->orWhereRaw("NULLIF(TRIM(COALESCE(invoice_syncs.raw_payload->'metadata'->>'mercadopago_payment_id','')), '') = payment_syncs.external_id")
                            ->orWhereRaw("NULLIF(TRIM(COALESCE(invoice_syncs.raw_payload->'metadata'->>'payment_reference','')), '') = payment_syncs.external_id");
                    });
            })
            ->orderBy('charge_created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, Enterprise>  $enterprises
     * @param  array<string, int>  $enterprisesByCode
     * @param  array<string, list<int>>  $enterprisesByEmail
     * @param  array<string, list<int>>  $enterprisesByNormalizedName
     * @return array{0: int|null, 1: string}
     */
    private function resolveEnterpriseId(
        PaymentSync $sync,
        Collection $enterprises,
        array $enterprisesByCode,
        array $enterprisesByEmail,
        array $enterprisesByNormalizedName,
    ): array {
        $customerId = $sync->customer_id !== null ? trim((string) $sync->customer_id) : '';
        if ($customerId !== '' && isset($enterprisesByCode[$customerId]))
        {
            return [$enterprisesByCode[$customerId], 'code'];
        }

        $email = strtolower(trim((string) ($sync->customer_email ?? '')));
        if ($email !== '' && isset($enterprisesByEmail[$email]) && count($enterprisesByEmail[$email]) === 1)
        {
            return [$enterprisesByEmail[$email][0], 'email'];
        }

        $settlementName = trim((string) ($sync->settlementPayerName() ?? ''));
        if ($settlementName !== '')
        {
            $matchedId = $this->matchEnterpriseBySettlementName(
                $settlementName,
                $enterprises,
                $enterprisesByNormalizedName,
            );
            if ($matchedId !== null)
            {
                return [$matchedId, 'settlement_name'];
            }
        }

        return [null, 'none'];
    }

    /**
     * @param  Collection<int, Enterprise>  $enterprises
     * @param  array<string, list<int>>  $enterprisesByNormalizedName
     */
    private function matchEnterpriseBySettlementName(
        string $settlementName,
        Collection $enterprises,
        array $enterprisesByNormalizedName,
    ): ?int {
        $normalizedPayer = $this->normalizePersonName($settlementName);
        if ($normalizedPayer === '')
        {
            return null;
        }

        if (isset($enterprisesByNormalizedName[$normalizedPayer])
            && count($enterprisesByNormalizedName[$normalizedPayer]) === 1)
        {
            return $enterprisesByNormalizedName[$normalizedPayer][0];
        }

        $matches = [];
        foreach ($enterprises as $enterprise)
        {
            $normalizedEnterprise = $this->normalizePersonName((string) $enterprise->name);
            if ($normalizedEnterprise === '')
            {
                continue;
            }

            if (
                $normalizedEnterprise === $normalizedPayer
                || str_contains($normalizedPayer, $normalizedEnterprise)
                || str_contains($normalizedEnterprise, $normalizedPayer)
            ) {
                $matches[(int) $enterprise->id] = true;
            }
        }

        if (count($matches) === 1)
        {
            return (int) array_key_first($matches);
        }

        return null;
    }

    private function normalizePersonName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = str_replace(['.', ',', ';'], ' ', $normalized);
        $normalized = preg_replace('/\b(s\.?\s*a\.?|s\.?\s*r\.?\s*l\.?|sa|srl|ltda|llc|inc)\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function invoiceDate(Invoice $invoice): ?CarbonInterface
    {
        if ($invoice->date instanceof CarbonInterface)
        {
            return $invoice->date;
        }

        if (filled($invoice->date))
        {
            try
            {
                return Carbon::parse($invoice->date);
            } catch (\Throwable)
            {
                return null;
            }
        }

        return null;
    }

    private function amountsMatch(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) <= self::AMOUNT_TOLERANCE;
    }

    private function amountMajor(PaymentSync $sync): float
    {
        $currency = strtoupper((string) $sync->currency);
        $cents = (int) $sync->amount_net_cents;

        if (in_array($currency, ['CLP', 'UYU', 'PYG'], true))
        {
            return (float) $cents;
        }

        return round($cents / 100, 2);
    }
}
