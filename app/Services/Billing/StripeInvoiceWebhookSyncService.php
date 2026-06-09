<?php

namespace App\Services\Billing;

use App\Models\Enterprise;
use App\Models\InvoiceSync;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class StripeInvoiceWebhookSyncService
{
    public function __construct(
        private readonly StripeInvoiceSyncUpserter $upserter,
        private readonly StripeInvoiceCoreImportService $coreImportService,
        private readonly StripeCollectedInvoicePaymentReconciliationService $paymentReconciliationService,
    ) {}

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    public function syncFromPayload(array $invoicePayload): bool
    {
        $externalId = trim((string) ($invoicePayload['id'] ?? ''));
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            return false;
        }

        $customerId = $this->resolveCustomerId($invoicePayload);
        $team = $this->resolveTeam($customerId, $externalId);

        if (! $team instanceof Team)
        {
            Log::debug('Stripe invoice webhook: team not resolved', [
                'external_id' => $externalId,
                'customer_id' => $customerId,
            ]);

            return false;
        }

        $sync = $this->upserter->upsertFromPayload($team->id, $invoicePayload);
        if (! $sync instanceof InvoiceSync)
        {
            return false;
        }

        $invoice = $this->coreImportService->importFromSyncRow(
            $sync->fresh(),
            fallbackEmail: true,
            linkCodeOnEmailMatch: true,
        );

        if ($invoice && (float) $invoice->balance <= 0)
        {
            $this->paymentReconciliationService->reconcileInvoice($invoice);
        }

        return $invoice !== null;
    }

    /**
     * @param  array<string, mixed>  $invoicePayload
     */
    private function resolveCustomerId(array $invoicePayload): ?string
    {
        $customer = $invoicePayload['customer'] ?? null;
        if (is_string($customer))
        {
            return $customer;
        }

        if (is_array($customer) && isset($customer['id']))
        {
            return (string) $customer['id'];
        }

        return null;
    }

    private function resolveTeam(?string $customerId, string $invoiceExternalId): ?Team
    {
        if ($customerId !== null && $customerId !== '')
        {
            $team = Team::findByStripeCustomerId($customerId);
            if ($team instanceof Team)
            {
                return $team;
            }

            $enterprise = Enterprise::withoutGlobalScopes()
                ->where('type_id', 1)
                ->where('code', $customerId)
                ->first();

            if ($enterprise)
            {
                $team = Team::query()->find($enterprise->team_id);
                if ($team instanceof Team)
                {
                    return $team;
                }
            }
        }

        $existingSync = InvoiceSync::query()
            ->where('provider', 'stripe')
            ->where('external_id', $invoiceExternalId)
            ->first();

        if ($existingSync instanceof InvoiceSync)
        {
            return Team::query()->find($existingSync->team_id);
        }

        return null;
    }
}
