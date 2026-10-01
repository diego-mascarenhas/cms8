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
        private readonly StripeCreditNoteCoreImportService $creditNoteCoreImportService,
        private readonly StripeCreditNoteCreatePayloadBuilder $payloadBuilder,
        private readonly StripeCreditNoteMetadataWriter $metadataWriter,
    ) {}

    /**
     * @return array{credit_note_id: string, number: string|null, external_number: string|null, amount: float|null}
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
        $client = $this->makeStripeClient($secret);

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

        // Stripe updates the invoice after a credit note (balance, credited amount).
        // That copy is stored on invoice_syncs only. Re-importing it would rewrite
        // the issued document: number, date, base, VAT, and lines stay as issued.
        $this->invoiceSyncRefresher->refreshFromStripe($client, $team->id, $externalId);

        // Separate abono document (negative in Hacienda export).
        $abono = $this->creditNoteCoreImportService->importFromStripePayload(
            (int) $team->id,
            $creditNote->toArray(),
            $invoice->fresh() ?? $invoice,
        );

        if ($abono instanceof Invoice)
        {
            $this->metadataWriter->push($client, $abono);
        }

        return [
            'credit_note_id' => (string) $creditNote->id,
            'number' => $abono?->number ?? ($creditNote->number !== null ? (string) $creditNote->number : null),
            'external_number' => $abono?->providerNumber(),
            'amount' => isset($creditNote->amount)
                ? round(((int) $creditNote->amount) / 100, 2)
                : null,
        ];
    }

    protected function makeStripeClient(string $secret): StripeClient
    {
        return new StripeClient($secret);
    }
}
