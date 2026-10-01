<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Services\Finance\CreditNoteNumberAllocator;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeCreditNoteMetadataWriter
{
    public function __construct(
        private readonly CreditNoteNumberAllocator $allocator,
    ) {}

    /**
     * Write the Humano credit-note number onto the Stripe credit note.
     * Stripe's own number stays unchanged. Existing metadata keys are kept.
     */
    public function push(StripeClient $client, Invoice $creditNote): bool
    {
        $externalId = trim((string) $creditNote->source_reference_id);
        $number = trim((string) $creditNote->number);

        if ($externalId === '' || ! str_starts_with($externalId, 'cn_'))
        {
            return false;
        }

        if (! $this->allocator->isHumanoCreditNoteNumber($number))
        {
            return false;
        }

        $metadata = [
            'humano_team_id' => (string) $creditNote->team_id,
            'humano_credit_note_id' => (string) $creditNote->id,
            'humano_credit_note_number' => $number,
        ];

        try
        {
            $client->creditNotes->update($externalId, [
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $exception)
        {
            Log::warning('Stripe credit note metadata update failed', [
                'invoice_id' => $creditNote->id,
                'external_id' => $externalId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        $this->mergeLocalMetadata($creditNote, $metadata);

        return true;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function mergeLocalMetadata(Invoice $creditNote, array $metadata): void
    {
        $sync = InvoiceSync::query()
            ->where('team_id', $creditNote->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $creditNote->source_reference_id)
            ->first();

        if (! $sync instanceof InvoiceSync)
        {
            return;
        }

        $payload = is_array($sync->raw_payload) ? $sync->raw_payload : [];
        $existing = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $payload['metadata'] = array_merge($existing, $metadata);
        $sync->raw_payload = $payload;
        $sync->save();
    }
}
