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
        private readonly StripeCreditNoteCoreImportService $creditNoteCoreImportService,
        private readonly StripeCreditNoteCreatePayloadBuilder $payloadBuilder,
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
            $stripeInvoice = $client->invoices->retrieve($externalId, [
                'expand' => ['lines.data'],
            ]);

            $createParams = $this->payloadBuilder->build(
                $externalId,
                $invoice,
                $reason,
                $stripeInvoice,
            );

            $creditNote = $client->creditNotes->create($createParams);
        } catch (ValidationException $exception)
        {
            throw $exception;
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

        // Refresh original invoice balance/status only — amounts stay as issued.
        $originalSnapshot = [
            'gross_amount' => (float) $invoice->gross_amount,
            'total_amount' => (float) $invoice->total_amount,
            'number' => (string) $invoice->number,
            'date' => (string) $invoice->date,
        ];

        $sync = $this->invoiceSyncRefresher->refreshFromStripe($client, $team->id, $externalId);
        if ($sync !== null)
        {
            $refreshed = $this->coreImportService->importFromSyncRow(
                $sync->fresh(),
                fallbackEmail: true,
                linkCodeOnEmailMatch: true,
            );

            // Guard: never let a credit-note refresh rewrite the original fiscal document amounts/number.
            if ($refreshed instanceof Invoice)
            {
                $needsRestore = abs((float) $refreshed->gross_amount - $originalSnapshot['gross_amount']) > 0.009
                    || abs((float) $refreshed->total_amount - $originalSnapshot['total_amount']) > 0.009
                    || (string) $refreshed->number !== $originalSnapshot['number'];

                if ($needsRestore)
                {
                    $refreshed->forceFill([
                        'gross_amount' => $originalSnapshot['gross_amount'],
                        'total_amount' => $originalSnapshot['total_amount'],
                        'number' => $originalSnapshot['number'],
                        'date' => $originalSnapshot['date'],
                    ])->save();
                }
            }
        }

        // Separate abono document (negative in Hacienda export).
        $this->creditNoteCoreImportService->importFromStripePayload(
            (int) $team->id,
            $creditNote->toArray(),
            $invoice->fresh() ?? $invoice,
        );

        return [
            'credit_note_id' => (string) $creditNote->id,
            'number' => $creditNote->number ?? null,
            'amount' => isset($creditNote->amount)
                ? round(((int) $creditNote->amount) / 100, 2)
                : null,
        ];
    }
}
