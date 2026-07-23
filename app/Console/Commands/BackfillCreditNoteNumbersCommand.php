<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Team;
use App\Services\Billing\StripeCreditNoteCoreImportService;
use App\Services\Finance\CreditNoteNumberAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Stripe\StripeClient;

class BackfillCreditNoteNumbersCommand extends Command
{
    protected $signature = 'invoices:backfill-credit-note-numbers
                            {--team_id= : Limit to one team}
                            {--sync-stripe : Pull credit notes from Stripe first}
                            {--dry-run : Preview without writing}';

    protected $description = 'Assign correlative Humano numbers (CN-0005-0001) to credit notes and optionally sync from Stripe';

    public function __construct(
        private readonly CreditNoteNumberAllocator $allocator,
        private readonly StripeCreditNoteCoreImportService $creditNoteImportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $syncStripe = (bool) $this->option('sync-stripe');
        $teamId = $this->option('team_id') !== null ? (int) $this->option('team_id') : null;

        $teams = Team::query()
            ->when($teamId, fn ($query) => $query->whereKey($teamId))
            ->orderBy('id')
            ->get();

        $imported = 0;
        $renumbered = 0;

        foreach ($teams as $team)
        {
            if ($syncStripe)
            {
                $imported += $this->syncStripeCreditNotes($team, $dryRun);
            }

            $renumbered += $this->renumberTeamCreditNotes((int) $team->id, $dryRun);
        }

        $this->info(
            "Imported: {$imported} | renumbered: {$renumbered}".
            ($dryRun ? ' | dry-run' : ''),
        );

        return self::SUCCESS;
    }

    private function syncStripeCreditNotes(Team $team, bool $dryRun): int
    {
        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '' || ! str_starts_with($secret, 'sk_'))
        {
            $this->warn("Team {$team->id}: missing/invalid stripe_secret, skip Stripe sync.");

            return 0;
        }

        $client = new StripeClient($secret);
        $imported = 0;
        $startingAfter = null;

        do
        {
            $params = ['limit' => 100];
            if ($startingAfter)
            {
                $params['starting_after'] = $startingAfter;
            }

            try
            {
                $page = $client->creditNotes->all($params);
            } catch (\Throwable $exception)
            {
                $this->error("Team {$team->id}: Stripe creditNotes.list failed: ".$exception->getMessage());

                return $imported;
            }

            foreach ($page->data as $creditNote)
            {
                $payload = $creditNote->toArray();
                $invoiceExternalId = trim((string) ($payload['invoice'] ?? ''));
                if ($invoiceExternalId === '')
                {
                    continue;
                }

                $original = Invoice::withoutGlobalScopes()
                    ->where('team_id', $team->id)
                    ->where('source_provider', 'stripe')
                    ->where('source_reference_id', $invoiceExternalId)
                    ->first();

                if (! $original)
                {
                    $this->line("Skip {$payload['id']}: original invoice {$invoiceExternalId} not found locally.");

                    continue;
                }

                if ($dryRun)
                {
                    $this->line("[dry-run] Would import {$payload['id']} for invoice {$original->number}");
                    $imported++;

                    continue;
                }

                $abono = $this->creditNoteImportService->importFromStripePayload(
                    (int) $team->id,
                    $payload,
                    $original,
                );

                if ($abono)
                {
                    $imported++;
                    $this->line("Imported {$abono->source_reference_id} → {$abono->number}");
                }
            }

            $startingAfter = $page->has_more ? end($page->data)->id : null;
        } while ($startingAfter);

        return $imported;
    }

    private function renumberTeamCreditNotes(int $teamId, bool $dryRun): int
    {
        $creditNotes = Invoice::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('type_id', 2)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($creditNotes->isEmpty())
        {
            return 0;
        }

        $counters = [];
        $renumbered = 0;

        foreach ($creditNotes as $creditNote)
        {
            $serie = $this->allocator->seriePrefixFromInvoiceNumber(
                $this->resolveSerieSource($creditNote),
            );

            $counters[$serie] = ($counters[$serie] ?? 0) + 1;
            $next = sprintf('CN-%s-%04d', $serie, $counters[$serie]);

            if ((string) $creditNote->number === $next)
            {
                $this->preserveProviderNumberOnSync($creditNote);

                continue;
            }

            if ($dryRun)
            {
                $this->line("[dry-run] {$creditNote->number} → {$next}");
                $renumbered++;

                continue;
            }

            $this->preserveProviderNumberOnSync($creditNote);

            $creditNote->number = $next;
            $creditNote->save();
            $renumbered++;
            $this->line("Renumbered id={$creditNote->id} → {$next}");
        }

        return $renumbered;
    }

    private function resolveSerieSource(Invoice $creditNote): string
    {
        if ($this->allocator->isHumanoCreditNoteNumber((string) $creditNote->number))
        {
            return (string) $creditNote->number;
        }

        $providerNumber = $creditNote->providerNumber();
        if (filled($providerNumber))
        {
            return (string) $providerNumber;
        }

        if (Schema::hasColumn('invoices', 'external_number') && filled($creditNote->getAttribute('external_number')))
        {
            return (string) $creditNote->getAttribute('external_number');
        }

        return (string) $creditNote->number;
    }

    /**
     * Persist Stripe's original document number on invoice_syncs before overwriting invoices.number.
     */
    private function preserveProviderNumberOnSync(Invoice $creditNote): void
    {
        $externalId = trim((string) $creditNote->source_reference_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'cn_'))
        {
            return;
        }

        $existing = InvoiceSync::query()
            ->where('team_id', $creditNote->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->first();

        $providerNumber = trim((string) ($existing?->number ?? ''));
        if ($providerNumber === '')
        {
            $providerNumber = trim((string) data_get($existing?->raw_payload, 'number', ''));
        }

        if ($providerNumber === '' && Schema::hasColumn('invoices', 'external_number'))
        {
            $providerNumber = trim((string) $creditNote->getAttribute('external_number'));
        }

        if ($providerNumber === '' && ! $this->allocator->isHumanoCreditNoteNumber((string) $creditNote->number))
        {
            $providerNumber = trim((string) $creditNote->number);
        }

        if ($providerNumber === '')
        {
            return;
        }

        $rawPayload = is_array($existing?->raw_payload) ? $existing->raw_payload : [];
        $rawPayload['id'] = $externalId;
        $rawPayload['number'] = $providerNumber;

        InvoiceSync::updateOrCreate(
            [
                'team_id' => $creditNote->team_id,
                'provider' => 'stripe',
                'external_id' => $externalId,
            ],
            [
                'number' => $providerNumber,
                'status' => $existing?->status ?: 'issued',
                'currency' => $existing?->currency ?: 'eur',
                'total' => $existing?->total ?? $creditNote->total_amount,
                'subtotal' => $existing?->subtotal ?? $creditNote->gross_amount,
                'paid' => true,
                'last_synced_at' => now(),
                'raw_payload' => $rawPayload,
            ],
        );
    }
}
