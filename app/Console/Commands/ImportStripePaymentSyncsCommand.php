<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentSync;
use App\Models\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportStripePaymentSyncsCommand extends Command
{
    protected $signature = 'payment-syncs:import-stripe
                            {--team_id= : Import only one team}
                            {--limit=500 : Max payment_syncs rows to process}
                            {--reconcile : Also update payments already linked to these sync rows}
                            {--fallback-email : Resolve enterprise by email when customer_id/code does not match}
                            {--link-code-on-email-match : When fallback by email succeeds uniquely, write Stripe customer_id into enterprises.code}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Stripe payment_syncs rows into core payments (idempotent by source reference)';

    public function handle(): int
    {
        if (! Schema::hasTable('payment_syncs'))
        {
            $this->error('Table payment_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;
        $reconcile = (bool) $this->option('reconcile');
        $fallbackEmail = (bool) $this->option('fallback-email');
        $linkCodeOnEmailMatch = (bool) $this->option('link-code-on-email-match');

        $query = PaymentSync::query()->where('provider', 'stripe');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        } else
        {
            $query->orderBy('team_id');
        }

        if (! $reconcile)
        {
            $query->whereNotExists(function ($q)
            {
                $q->from('payments')
                    ->whereColumn('payments.source_reference_id', 'payment_syncs.external_id')
                    ->whereColumn('payments.team_id', 'payment_syncs.team_id')
                    ->where('payments.source_provider', 'stripe');
            });
        }

        $query
            ->orderByRaw('charge_created_at IS NULL')
            ->orderBy('charge_created_at')
            ->orderBy('id');

        $rows = $query->limit($limit)->get();

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row)
        {
            if (! $row instanceof PaymentSync)
            {
                continue;
            }

            $processed++;

            $status = strtolower((string) $row->status);
            if ($status !== 'succeeded')
            {
                $skipped++;

                continue;
            }

            $netCents = (int) $row->amount_net_cents;
            if ($netCents <= 0)
            {
                $skipped++;

                continue;
            }

            $currency = strtoupper((string) $row->currency);
            $amountMajor = $this->majorAmountFromCents($netCents, $currency);

            [$enterpriseId, $resolutionMode] = $this->resolveEnterpriseId(
                $row,
                $fallbackEmail,
                $linkCodeOnEmailMatch,
                $dryRun,
            );

            if (! $enterpriseId)
            {
                $skipped++;
                $reason = $fallbackEmail ? 'customer_id/code or unique email' : 'customer_id/code';
                $this->warn("Skip {$row->external_id}: enterprise not found by {$reason} for team {$row->team_id}");

                continue;
            }

            $invoiceId = $this->resolveInvoiceId($row->team_id, $row->invoice_external_id);
            $date = $row->charge_created_at
                ? $row->charge_created_at->toDateString()
                : now()->toDateString();

            $description = (string) ($row->description ?? '');
            $remarks = trim($description) !== ''
                ? Str::limit($description, 500)
                : 'Stripe '.$row->external_id.($resolutionMode !== 'none' ? " ({$resolutionMode})" : '');

            if ($dryRun)
            {
                $this->line("[dry-run] payment team={$row->team_id} ch={$row->external_id} amount={$amountMajor} date={$date}");
                $created++;

                continue;
            }

            $accountId = $this->ensureStripePaymentAccount($row->team_id);
            $typeId = $this->resolveStripePaymentTypeId();
            if ($accountId === null || $typeId === null)
            {
                $skipped++;
                $this->warn("Skip {$row->external_id}: no payment account or type for team {$row->team_id}.");

                continue;
            }

            $existing = Payment::withoutGlobalScopes()
                ->where('team_id', $row->team_id)
                ->where('source_provider', 'stripe')
                ->where('source_reference_id', $row->external_id)
                ->first();

            $payload = [
                'enterprise_id' => $enterpriseId,
                'transaction_type' => TransactionType::INCOME,
                'date' => $date,
                'invoice_id' => $invoiceId,
                'account_id' => $accountId,
                'type_id' => $typeId,
                'amount' => $amountMajor,
                'remarks' => $remarks,
                'status' => 2,
                'source_provider' => 'stripe',
                'source_reference_id' => $row->external_id,
                'source_synced_at' => $row->last_synced_at ?? now(),
            ];

            if ($existing)
            {
                $existing->fill($payload);
                $existing->save();
                $updated++;
            } else
            {
                Payment::withoutGlobalScopes()->create(array_merge(
                    $payload,
                    [
                        'team_id' => $row->team_id,
                    ],
                ));
                $created++;
            }
        }

        $this->info(
            "Processed: {$processed} | created: {$created} | updated: {$updated} | skipped: {$skipped}".
            ($reconcile ? ' | reconcile' : ' | pending-only').
            ($dryRun ? ' | dry-run' : '')
        );

        return self::SUCCESS;
    }

    private function majorAmountFromCents(int $cents, string $currency): float
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        $divisor = in_array($currency, $zeroDecimal, true) ? 1 : 100;
        $value = $divisor === 1 ? (float) $cents : round($cents / 100, 2);

        return max(0.0, $value);
    }

    private function ensureStripePaymentAccount(int $teamId): ?int
    {
        $account = PaymentAccount::withoutGlobalScopes()->firstOrCreate(
            [
                'team_id' => $teamId,
                'code' => 'stripe',
            ],
            [
                'name' => 'Stripe',
                'symbol' => null,
                'currency_id' => null,
                'status' => 1,
            ],
        );

        return (int) $account->id;
    }

    private function resolveStripePaymentTypeId(): ?int
    {
        $id = PaymentType::query()->where('name', 'Stripe')->value('id');
        if ($id !== null)
        {
            return (int) $id;
        }

        $fallback = PaymentType::query()->orderBy('id')->value('id');

        return $fallback !== null ? (int) $fallback : null;
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveEnterpriseId(
        PaymentSync $row,
        bool $fallbackEmail,
        bool $linkCodeOnEmailMatch,
        bool $dryRun,
    ): array {
        $customerId = $row->customer_id !== null ? trim((string) $row->customer_id) : '';

        if ($customerId !== '')
        {
            $enterprise = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', 1)
                ->where('code', $customerId)
                ->first();

            if ($enterprise)
            {
                return [$enterprise->id, 'code'];
            }
        }

        if (! $fallbackEmail)
        {
            return [null, 'none'];
        }

        $email = strtolower(trim((string) ($row->customer_email ?? '')));
        if ($email === '')
        {
            return [null, 'none'];
        }

        $emailMatches = Enterprise::query()
            ->where('team_id', $row->team_id)
            ->where('type_id', 1)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get();

        if ($emailMatches->count() !== 1)
        {
            return [null, 'none'];
        }

        /** @var Enterprise $matched */
        $matched = $emailMatches->first();

        if ($linkCodeOnEmailMatch && $customerId !== '' && blank($matched->code) && ! $dryRun)
        {
            $matched->code = $customerId;
            $matched->save();
        }

        return [$matched->id, 'email'];
    }

    private function resolveInvoiceId(int $teamId, ?string $invoiceExternalId): ?int
    {
        if ($invoiceExternalId === null || $invoiceExternalId === '' || ! str_starts_with($invoiceExternalId, 'in_'))
        {
            return null;
        }

        $id = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $invoiceExternalId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
