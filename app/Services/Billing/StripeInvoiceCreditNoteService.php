<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeInvoiceCreditNoteService
{
    public function __construct(
        private readonly StripeInvoiceSyncRefresher $invoiceSyncRefresher,
        private readonly StripeInvoiceCoreImportService $coreImportService,
    ) {}

    /**
     * @return array{credit_note_id: string, number: string|null, amount: float|null}
     */
    public function issueForInvoice(Invoice $invoice, string $reason): array
    {
        $team = Team::query()->find($invoice->team_id);
        if (! $team instanceof Team)
        {
            throw ValidationException::withMessages([
                'reason' => __('invoice_credit_note.errors.team_not_found'),
            ]);
        }

        $secret = trim((string) $team->getSetting('stripe_secret'));
        if ($secret === '')
        {
            throw ValidationException::withMessages([
                'reason' => __('invoice_credit_note.errors.stripe_not_configured'),
            ]);
        }

        $externalId = (string) $invoice->source_reference_id;
        $client = new StripeClient($secret);

        try
        {
            $creditNote = $client->creditNotes->create([
                'invoice' => $externalId,
                'reason' => $reason,
                'metadata' => [
                    'humano_invoice_id' => (string) $invoice->id,
                    'humano_team_id' => (string) $invoice->team_id,
                ],
            ]);
        } catch (ApiErrorException $exception)
        {
            Log::warning('Stripe credit note creation failed', [
                'invoice_id' => $invoice->id,
                'external_id' => $externalId,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'reason' => $exception->getMessage(),
            ]);
        }

        $sync = $this->invoiceSyncRefresher->refreshFromStripe($client, $team->id, $externalId);
        if ($sync !== null)
        {
            $this->coreImportService->importFromSyncRow(
                $sync->fresh(),
                fallbackEmail: true,
                linkCodeOnEmailMatch: true,
            );
        }

        return [
            'credit_note_id' => (string) $creditNote->id,
            'number' => $creditNote->number ?? null,
            'amount' => isset($creditNote->amount)
                ? round(((int) $creditNote->amount) / 100, 2)
                : null,
        ];
    }
}
