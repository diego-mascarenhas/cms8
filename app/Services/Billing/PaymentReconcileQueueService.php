<?php

namespace App\Services\Billing;

use App\Models\BankStatementLine;
use App\Models\Enterprise;
use App\Models\Payment;
use App\Models\PaymentSync;
use Illuminate\Support\Collection;

class PaymentReconcileQueueService
{
    public function __construct(
        private readonly MercadoPagoAutoAssignMatcherService $autoAssignMatcher,
    ) {}

    /**
     * Build a unified reconcile queue: pending suggestions first, then imported mismatches.
     *
     * @return list<array<string, mixed>>
     */
    public function buildQueue(int $teamId, int $suggestionLimit = 25, int $mismatchLimit = 25): array
    {
        $suggestions = collect($this->autoAssignMatcher->buildSuggestions($teamId, $suggestionLimit, useAi: false))
            ->map(function (array $item): array
            {
                return array_merge($item, [
                    'kind' => 'suggestion',
                    'match_kind' => $item['kind'] ?? null,
                    'provider' => 'mercadopago',
                ]);
            })
            ->values()
            ->all();

        $mismatches = $this->buildMismatches($teamId, $mismatchLimit);

        return array_values(array_merge($suggestions, $mismatches));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildMismatches(int $teamId, int $limit = 25): array
    {
        $payments = Payment::withoutGlobalScopes()
            ->with(['enterprise', 'invoice'])
            ->where('team_id', $teamId)
            ->where('source_provider', 'mercadopago')
            ->whereNotNull('source_reference_id')
            ->orderByDesc('date')
            ->limit(200)
            ->get();

        if ($payments->isEmpty())
        {
            return [];
        }

        $externalIds = $payments
            ->map(fn (Payment $payment) => $this->baseExternalId((string) $payment->source_reference_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var Collection<string, PaymentSync> $syncs */
        $syncs = PaymentSync::query()
            ->where('team_id', $teamId)
            ->where('provider', 'mercadopago')
            ->whereIn('external_id', $externalIds)
            ->get()
            ->keyBy(fn (PaymentSync $sync) => (string) $sync->external_id);

        /** @var Collection<string, BankStatementLine> $lines */
        $lines = BankStatementLine::query()
            ->whereIn('external_id', $externalIds)
            ->whereHas('statement', function ($query) use ($teamId): void
            {
                $query->where('team_id', $teamId)
                    ->where('provider', 'mercadopago');
            })
            ->get()
            ->keyBy(fn (BankStatementLine $line) => (string) $line->external_id);

        $items = [];
        $seenSyncIds = [];

        foreach ($payments as $payment)
        {
            $externalId = $this->baseExternalId((string) $payment->source_reference_id);
            if ($externalId === '')
            {
                continue;
            }

            $sync = $syncs->get($externalId);
            if (! $sync instanceof PaymentSync)
            {
                continue;
            }

            if (isset($seenSyncIds[(int) $sync->id]))
            {
                continue;
            }

            $line = $lines->get($externalId);
            if ($line instanceof BankStatementLine && $line->isDismissed())
            {
                continue;
            }

            if ($sync->isReconcileDismissed())
            {
                continue;
            }

            $payerName = $line?->payer_name ?: $sync->settlementPayerName();
            $payerIdNumber = $line?->payer_id_number ?: $sync->settlementPayerIdNumber();
            if (blank($payerName))
            {
                continue;
            }

            $enterprise = $payment->enterprise;
            if (! $enterprise instanceof Enterprise)
            {
                continue;
            }

            if ($this->namesCompatible((string) $payerName, (string) $enterprise->name))
            {
                continue;
            }

            $seenSyncIds[(int) $sync->id] = true;

            $invoice = $payment->invoice;
            $items[] = [
                'kind' => 'mismatch',
                'match_kind' => 'mismatch',
                'provider' => 'mercadopago',
                'sync_id' => (int) $sync->id,
                'payment_id' => (int) $payment->id,
                'statement_line_id' => $line?->id,
                'enterprise_id' => (int) $enterprise->id,
                'enterprise_name' => (string) $enterprise->name,
                'invoice_ids' => $invoice ? [(int) $invoice->id] : [],
                'invoice_numbers' => $invoice
                    ? [(string) ($invoice->number ?: '#'.$invoice->id)]
                    : [],
                'confidence' => 1.0,
                'reason' => __('payment_sync.reconcile.reason_mismatch'),
                'amount' => (float) $payment->amount,
                'currency' => strtoupper((string) ($sync->currency ?: 'ARS')),
                'payment_date' => $payment->date?->format('d/m/Y'),
                'external_id' => $externalId,
                'identification_code' => $sync->identificationCode(),
                'settlement_payer_name' => $payerName,
                'settlement_payer_id_number' => $payerIdNumber,
            ];

            if (count($items) >= $limit)
            {
                break;
            }
        }

        return $items;
    }

    public function dismissMismatch(int $teamId, array $item): void
    {
        $lineId = (int) ($item['statement_line_id'] ?? 0);
        if ($lineId > 0)
        {
            $line = BankStatementLine::query()
                ->whereKey($lineId)
                ->whereHas('statement', fn ($query) => $query->where('team_id', $teamId))
                ->first();

            if ($line instanceof BankStatementLine)
            {
                $line->markDismissed();
            }
        }

        $syncId = (int) ($item['sync_id'] ?? 0);
        if ($syncId > 0)
        {
            $sync = PaymentSync::query()
                ->whereKey($syncId)
                ->where('team_id', $teamId)
                ->first();

            if ($sync instanceof PaymentSync)
            {
                $sync->markReconcileDismissed();
            }
        }
    }

    private function baseExternalId(string $sourceReferenceId): string
    {
        $trimmed = trim($sourceReferenceId);
        if ($trimmed === '')
        {
            return '';
        }

        return explode(':', $trimmed, 2)[0];
    }

    private function namesCompatible(string $payerName, string $enterpriseName): bool
    {
        $normalizedPayer = $this->normalizePersonName($payerName);
        $normalizedEnterprise = $this->normalizePersonName($enterpriseName);

        if ($normalizedPayer === '' || $normalizedEnterprise === '')
        {
            return false;
        }

        return $normalizedPayer === $normalizedEnterprise
            || str_contains($normalizedPayer, $normalizedEnterprise)
            || str_contains($normalizedEnterprise, $normalizedPayer);
    }

    private function normalizePersonName(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = str_replace(['.', ',', ';'], ' ', $normalized);
        $normalized = preg_replace('/\b(s\.?\s*a\.?|s\.?\s*r\.?\s*l\.?|sa|srl|ltda|llc|inc)\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
