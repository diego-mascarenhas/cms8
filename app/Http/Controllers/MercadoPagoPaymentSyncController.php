<?php

namespace App\Http\Controllers;

use App\DataTables\MercadoPagoPaymentSyncDataTable;
use App\Http\Requests\ImportMercadoPagoPaymentSyncRequest;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\MercadoPagoInvoiceSuggestionService;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\StripeInvoiceCoreImportService;
use App\Support\MercadoPagoPaidInvoiceLinker;
use App\Support\PaymentInvoiceLinkOptionFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MercadoPagoPaymentSyncController extends Controller
{
    public function __construct(
        private readonly MercadoPagoPaymentImportService $importService,
        private readonly MercadoPagoInvoiceSuggestionService $suggestionService,
        private readonly StripeInvoiceCoreImportService $stripeInvoiceImportService,
    ) {}

    public function index(MercadoPagoPaymentSyncDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        return $dataTable->render('payments.syncs.mercadopago.index');
    }

    public function assign(Request $request, PaymentSync $sync): View|RedirectResponse
    {
        $this->authorize('create', Payment::class);
        $this->ensureTeamSync($sync);

        if ($this->importService->isAlreadyImported($sync))
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.already_imported'));
        }

        if (strtolower((string) $sync->status) !== 'approved')
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.not_approved'));
        }

        $linkedInvoice = $this->resolveOrMaterializeLinkedStripeInvoice($sync);
        if ($linkedInvoice instanceof Invoice)
        {
            $this->importService->importOutOfBandLinkForStripeInvoice($linkedInvoice);

            return redirect()->route('invoice.show', $linkedInvoice->id);
        }

        $teamId = (int) auth()->user()->currentTeam->id;
        $enterprises = Enterprise::query()
            ->where('team_id', $teamId)
            ->withStripeCustomerCode()
            ->withMercadoPagoAssignableInvoices()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'email']);

        $selectedEnterpriseId = (int) ($request->query('enterprise_id') ?: old('enterprise_id'));
        $amountMajor = $this->amountMajor($sync);
        $invoices = collect();
        $paidUnlinkedInvoices = collect();
        $suggestions = [];
        $selectedInvoiceIds = array_map('intval', (array) old('invoice_ids', []));

        if ($selectedEnterpriseId > 0)
        {
            $this->stripeInvoiceImportService->importOpenSyncsForEnterprise($teamId, $selectedEnterpriseId);
            $this->stripeInvoiceImportService->importPaidUnlinkedSyncsForEnterprise($teamId, $selectedEnterpriseId);

            $invoices = Invoice::query()
                ->where('team_id', $teamId)
                ->where('enterprise_id', $selectedEnterpriseId)
                ->where('operation', 'sell')
                ->where('balance', '>', 0)
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            $paidUnlinkedInvoices = MercadoPagoPaidInvoiceLinker::paidUnlinkedForEnterprise(
                $teamId,
                $selectedEnterpriseId,
            );

            $suggestions = $this->suggestionService->suggest($invoices, $amountMajor);

            $paidForSuggestions = $paidUnlinkedInvoices->map(function (Invoice $invoice): Invoice
            {
                $clone = clone $invoice;
                $clone->balance = $invoice->total_amount;

                return $clone;
            });

            foreach ($this->suggestionService->suggest($paidForSuggestions, $amountMajor) as $paidSuggestion)
            {
                $paidSuggestion['kind'] = 'paid_link';
                $suggestions[] = $paidSuggestion;
            }

            if ($selectedInvoiceIds === [] && count($suggestions) === 1)
            {
                $selectedInvoiceIds = $suggestions[0]['invoice_ids'];
            }
        }

        return view('payments.syncs.mercadopago.assign', [
            'sync' => $sync,
            'enterprises' => $enterprises,
            'invoices' => $invoices,
            'paidUnlinkedInvoices' => $paidUnlinkedInvoices,
            'suggestions' => $suggestions,
            'selectedEnterpriseId' => $selectedEnterpriseId,
            'selectedInvoiceIds' => $selectedInvoiceIds,
            'amountMajor' => $amountMajor,
            'invoiceOptionFormatter' => PaymentInvoiceLinkOptionFormatter::class,
        ]);
    }

    public function linkedInvoice(PaymentSync $sync): RedirectResponse
    {
        $this->authorize('viewAny', Payment::class);
        $this->ensureTeamSync($sync);

        $invoice = $this->resolveOrMaterializeLinkedStripeInvoice($sync);
        if (! $invoice instanceof Invoice)
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.stripe_invoice_missing'));
        }

        $this->importService->importOutOfBandLinkForStripeInvoice($invoice);

        return redirect()->route('invoice.show', $invoice->id);
    }

    public function import(ImportMercadoPagoPaymentSyncRequest $request, PaymentSync $sync): RedirectResponse
    {
        $this->ensureTeamSync($sync);

        if ($this->importService->isAlreadyImported($sync))
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.already_imported'));
        }

        if (strtolower((string) $sync->status) !== 'approved')
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.not_approved'));
        }

        $validated = $request->validated();
        $invoiceIds = $request->invoiceIds();
        $linkPayerCode = $request->boolean('link_payer_code', true);

        $payment = $this->importService->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: $linkPayerCode,
            dryRun: false,
            forceEnterpriseId: (int) $validated['enterprise_id'],
            forceInvoiceIds: $invoiceIds,
            remarksOverride: $validated['remarks'] ?? null,
        );

        if ($payment === null)
        {
            return redirect()
                ->route('payments.syncs.mercadopago.assign', [
                    'sync' => $sync,
                    'enterprise_id' => (int) $validated['enterprise_id'],
                ])
                ->withInput()
                ->with('error', __('payment_sync.mercadopago.errors.import_failed'));
        }

        $message = count($invoiceIds) > 1
            ? __('payment_sync.mercadopago.success_split', [
                'reference' => $sync->external_id,
                'count' => count($invoiceIds),
            ])
            : __('payment_sync.mercadopago.success', [
                'reference' => $sync->external_id,
            ]);

        $redirect = redirect()
            ->route('payments.syncs.mercadopago.index')
            ->with('success', $message);

        if ($this->stripeInvoiceStillOpenAfterImport($payment))
        {
            $redirect->with('warning', __('payment_sync.mercadopago.warnings.stripe_still_open'));
        }

        return $redirect;
    }

    private function resolveOrMaterializeLinkedStripeInvoice(PaymentSync $sync): ?Invoice
    {
        $invoiceSync = $this->findLinkedStripeInvoiceSync($sync);
        if (! $invoiceSync instanceof InvoiceSync)
        {
            return null;
        }

        $existing = Invoice::withoutGlobalScopes()
            ->where('team_id', $sync->team_id)
            ->where('source_provider', 'stripe')
            ->where('source_reference_id', $invoiceSync->external_id)
            ->first();

        if ($existing instanceof Invoice)
        {
            return $existing;
        }

        return $this->stripeInvoiceImportService->importFromSyncRow(
            $invoiceSync,
            fallbackEmail: true,
            linkCodeOnEmailMatch: false,
            dryRun: false,
        );
    }

    private function findLinkedStripeInvoiceSync(PaymentSync $sync): ?InvoiceSync
    {
        $references = $sync->stripeMatchReferences();
        if ($references === [])
        {
            return null;
        }

        return InvoiceSync::query()
            ->where('team_id', $sync->team_id)
            ->where('provider', 'stripe')
            ->where(function ($query) use ($references): void
            {
                foreach ($references as $reference)
                {
                    $query->orWhereRaw(
                        "TRIM(COALESCE(raw_payload->'metadata'->>'payment_reference', '')) = ?",
                        [$reference],
                    )->orWhereRaw(
                        "TRIM(COALESCE(
                            raw_payload->'metadata'->>'mercadopago_id',
                            raw_payload->'metadata'->>'mercadopago_payment_id',
                            ''
                        )) = ?",
                        [$reference],
                    );
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private function stripeInvoiceStillOpenAfterImport(?\App\Models\Payment $payment): bool
    {
        if (! $payment?->invoice_id)
        {
            return false;
        }

        $invoice = Invoice::withoutGlobalScopes()->whereKey($payment->invoice_id)->first();
        if (! $invoice || strtolower((string) $invoice->source_provider) !== 'stripe')
        {
            return false;
        }

        $externalId = trim((string) $invoice->source_reference_id);
        if ($externalId === '' || ! str_starts_with($externalId, 'in_'))
        {
            return false;
        }

        $sync = InvoiceSync::query()
            ->where('team_id', $invoice->team_id)
            ->where('provider', 'stripe')
            ->where('external_id', $externalId)
            ->first();

        if (! $sync)
        {
            return true;
        }

        return ! $sync->paid && strtolower((string) $sync->status) !== 'paid';
    }

    private function ensureTeamSync(PaymentSync $sync): void
    {
        abort_unless(
            (int) $sync->team_id === (int) auth()->user()->currentTeam->id,
            404,
        );
        abort_unless(strtolower((string) $sync->provider) === 'mercadopago', 404);
    }

    private function amountMajor(PaymentSync $sync): float
    {
        $currency = strtoupper((string) $sync->currency);
        $cents = (int) $sync->amount_net_cents;
        $zeroDecimal = ['CLP', 'UYU', 'PYG'];

        if (in_array($currency, $zeroDecimal, true))
        {
            return (float) $cents;
        }

        return round($cents / 100, 2);
    }
}
