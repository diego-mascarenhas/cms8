<?php

namespace App\Console\Commands;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ImportStripeInvoiceSyncsCommand extends Command
{
    protected $signature = 'invoice-syncs:import-stripe
                            {--team_id= : Import only one team}
                            {--limit=500 : Max sync rows to process}
                            {--dry-run : Preview without writing}';

    protected $description = 'Map Stripe invoice_syncs rows into core invoices table (idempotent by source reference)';

    public function handle(): int
    {
        if (! Schema::hasTable('invoice_syncs'))
        {
            $this->error('Table invoice_syncs does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;

        $query = InvoiceSync::query()
            ->where('provider', 'stripe')
            ->orderBy('invoice_created_at')
            ->orderBy('id');

        if ($teamId)
        {
            $query->where('team_id', $teamId);
        }

        $rows = $query->limit($limit)->get();

        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $invoicesHasCurrency = Schema::hasColumn('invoices', 'currency');

        foreach ($rows as $row)
        {
            $processed++;

            $enterpriseId = Enterprise::query()
                ->where('team_id', $row->team_id)
                ->where('type_id', 1)
                ->where('code', $row->customer_id)
                ->value('id');

            if (! $enterpriseId)
            {
                $skipped++;
                $this->warn("Skip {$row->external_id}: enterprise not found by customer_id/code for team {$row->team_id}");

                continue;
            }

            $date = $row->invoice_created_at
                ? Carbon::parse($row->invoice_created_at)->toDateString()
                : now()->toDateString();
            $dueDate = $row->invoice_due_date
                ? Carbon::parse($row->invoice_due_date)->toDateString()
                : null;

            $gross = $this->normalizeAmount($row->subtotal ?? $row->total ?? $row->amount_due ?? 0);
            $discount = $this->normalizeNullableAmount($row->total_discount_amount);
            $total = $this->normalizeAmount($row->total ?? $row->amount_due ?? $gross);
            $balance = $this->normalizeAmount($row->amount_remaining ?? $total);

            $payload = [
                'team_id' => $row->team_id,
                'enterprise_id' => $enterpriseId,
                'billing_id' => null,
                'type_id' => 1,
                'operation' => 'sell',
                'number' => $row->number ?: $row->external_id,
                'date' => $date,
                'due_date' => $dueDate,
                'gross_amount' => $gross,
                'discount' => $discount,
                'total_amount' => $total,
                'balance' => $balance,
                'status' => $this->mapStripeStatusToInvoiceStatus((string) $row->status),
                'source_provider' => 'stripe',
                'source_reference_id' => $row->external_id,
                'source_synced_at' => $row->last_synced_at ?? now(),
            ];

            if ($invoicesHasCurrency)
            {
                $payload['currency'] = strtoupper((string) ($row->currency ?: 'USD'));
            }

            if ($dryRun)
            {
                $this->line("[dry-run] upsert invoice for stripe external_id={$row->external_id}, team={$row->team_id}");

                continue;
            }

            $existing = Invoice::withoutGlobalScopes()
                ->where('source_provider', 'stripe')
                ->where('source_reference_id', $row->external_id)
                ->first();

            if ($existing)
            {
                $existing->fill($payload);
                $existing->save();
                $updated++;
            } else
            {
                Invoice::withoutGlobalScopes()->create($payload);
                $created++;
            }
        }

        $this->info("Processed: {$processed} | created: {$created} | updated: {$updated} | skipped: {$skipped}".($dryRun ? ' | dry-run' : ''));

        return self::SUCCESS;
    }

    private function mapStripeStatusToInvoiceStatus(string $status): int
    {
        return match (strtolower($status))
        {
            'draft' => 9,
            'open' => 1,
            'paid' => 2,
            'void' => 3,
            'uncollectible' => 7,
            default => 7,
        };
    }

    private function normalizeAmount(mixed $amount): float
    {
        $value = (float) $amount;
        if ($value < 0)
        {
            return 0.0;
        }

        return round($value, 2);
    }

    private function normalizeNullableAmount(mixed $amount): ?float
    {
        if ($amount === null)
        {
            return null;
        }

        return $this->normalizeAmount($amount);
    }
}
