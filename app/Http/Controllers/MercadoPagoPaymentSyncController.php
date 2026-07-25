<?php

namespace App\Http\Controllers;

use App\DataTables\MercadoPagoPaymentSyncDataTable;
use App\Http\Requests\ImportMercadoPagoPaymentSyncRequest;
use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\InvoiceSync;
use App\Models\Payment;
use App\Models\PaymentSync;
use App\Services\Billing\MercadoPagoAutoAssignMatcherService;
use App\Services\Billing\MercadoPagoInvoiceSuggestionService;
use App\Services\Billing\MercadoPagoPaymentImportService;
use App\Services\Billing\StripeInvoiceCoreImportService;
use App\Services\Billing\StripeInvoiceSyncRefresher;
use App\Support\MercadoPagoPaidInvoiceLinker;
use App\Support\PaymentInvoiceLinkOptionFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MercadoPagoPaymentSyncController extends Controller
{
    private const AUTO_ASSIGN_SESSION_KEY = 'mercadopago_auto_assign_queue';

    public function __construct(
        private readonly MercadoPagoPaymentImportService $importService,
        private readonly MercadoPagoInvoiceSuggestionService $suggestionService,
        private readonly StripeInvoiceCoreImportService $stripeInvoiceImportService,
        private readonly StripeInvoiceSyncRefresher $stripeInvoiceSyncRefresher,
        private readonly MercadoPagoAutoAssignMatcherService $autoAssignMatcher,
    ) {}

    public function index(MercadoPagoPaymentSyncDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        return $dataTable->render('payments.syncs.mercadopago.index');
    }

    public function autoAssign(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $teamId = (int) auth()->user()->currentTeam->id;
        $rebuild = $request->boolean('rebuild') || ! $request->session()->has(self::AUTO_ASSIGN_SESSION_KEY);

        if ($rebuild)
        {
            $suggestions = $this->autoAssignMatcher->buildSuggestions($teamId, useAi: false);
            $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, [
                'items' => $suggestions,
                'index' => 0,
                'accepted' => 0,
                'skipped' => 0,
            ]);
        }

        $queue = $request->session()->get(self::AUTO_ASSIGN_SESSION_KEY, [
            'items' => [],
            'index' => 0,
            'accepted' => 0,
            'skipped' => 0,
        ]);

        $items = array_values($queue['items'] ?? []);
        $index = (int) ($queue['index'] ?? 0);

        if ($items === [] || $index >= count($items))
        {
            return view('payments.syncs.mercadopago.auto-assign', [
                'current' => null,
                'index' => $index,
                'total' => count($items),
                'accepted' => (int) ($queue['accepted'] ?? 0),
                'skipped' => (int) ($queue['skipped'] ?? 0),
                'done' => true,
            ]);
        }

        return view('payments.syncs.mercadopago.auto-assign', [
            'current' => $items[$index],
            'index' => $index,
            'total' => count($items),
            'accepted' => (int) ($queue['accepted'] ?? 0),
            'skipped' => (int) ($queue['skipped'] ?? 0),
            'done' => false,
        ]);
    }

    public function autoAssignAccept(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $queue = $request->session()->get(self::AUTO_ASSIGN_SESSION_KEY);
        if (! is_array($queue) || empty($queue['items']))
        {
            return redirect()->route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]);
        }

        $items = array_values($queue['items']);
        $index = (int) ($queue['index'] ?? 0);
        $current = $items[$index] ?? null;

        if (! is_array($current))
        {
            return redirect()->route('payments.syncs.mercadopago.auto-assign');
        }

        $sync = PaymentSync::query()->find((int) $current['sync_id']);
        if (! $sync || (int) $sync->team_id !== (int) auth()->user()->currentTeam->id)
        {
            $queue['index'] = $index + 1;
            $queue['skipped'] = (int) ($queue['skipped'] ?? 0) + 1;
            $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, $queue);

            return redirect()
                ->route('payments.syncs.mercadopago.auto-assign')
                ->with('error', __('payment_sync.mercadopago.errors.import_failed'));
        }

        $this->ensureTeamSync($sync);

        if ($this->importService->isAlreadyImported($sync))
        {
            $queue['index'] = $index + 1;
            $queue['skipped'] = (int) ($queue['skipped'] ?? 0) + 1;
            $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, $queue);

            return redirect()
                ->route('payments.syncs.mercadopago.auto-assign')
                ->with('warning', __('payment_sync.mercadopago.errors.already_imported'));
        }

        $payment = $this->importService->importFromPaymentSync(
            $sync,
            fallbackEmail: false,
            linkCodeOnEmailMatch: false,
            dryRun: false,
            forceEnterpriseId: (int) $current['enterprise_id'],
            forceInvoiceIds: array_map('intval', $current['invoice_ids'] ?? []),
            remarksOverride: null,
        );

        $queue['index'] = $index + 1;

        if ($payment === null)
        {
            $queue['skipped'] = (int) ($queue['skipped'] ?? 0) + 1;
            $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, $queue);

            return redirect()
                ->route('payments.syncs.mercadopago.auto-assign')
                ->with('error', __('payment_sync.mercadopago.errors.import_failed'));
        }

        $queue['accepted'] = (int) ($queue['accepted'] ?? 0) + 1;
        $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, $queue);

        return redirect()
            ->route('payments.syncs.mercadopago.auto-assign')
            ->with('success', __('payment_sync.mercadopago.success', [
                'reference' => $sync->external_id,
            ]));
    }

    public function autoAssignSkip(Request $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $queue = $request->session()->get(self::AUTO_ASSIGN_SESSION_KEY);
        if (! is_array($queue) || empty($queue['items']))
        {
            return redirect()->route('payments.syncs.mercadopago.auto-assign', ['rebuild' => 1]);
        }

        $queue['index'] = (int) ($queue['index'] ?? 0) + 1;
        $queue['skipped'] = (int) ($queue['skipped'] ?? 0) + 1;
        $request->session()->put(self::AUTO_ASSIGN_SESSION_KEY, $queue);

        return redirect()->route('payments.syncs.mercadopago.auto-assign');
    }

    public function assign(Request $request, PaymentSync $sync): View|RedirectResponse
    {
        $this->authorize('create', Payment::class);
        $this->ensureTeamSync($sync);

        if ($this->importService->isAlreadyImported($sync))
        {
            return $this->redirectAfterMetadataBackfill($sync);
        }

        if (strtolower((string) $sync->status) !== 'approved')
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.not_approved'));
        }

        $metadataLinkedPayment = $this->importService->importFromExistingStripeMetadataLinks($sync);
        if ($metadataLinkedPayment instanceof Payment)
        {
            return $this->redirectAfterMetadataBackfill($sync);
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
            $enterprise = Enterprise::query()
                ->where('team_id', $teamId)
                ->whereKey($selectedEnterpriseId)
                ->first();

            if ($enterprise && filled($enterprise->code))
            {
                $this->stripeInvoiceSyncRefresher->syncPaidInvoicesForCustomer(
                    $teamId,
                    (string) $enterprise->code,
                );
            }

            $this->stripeInvoiceImportService->importOpenSyncsForEnterprise($teamId, $selectedEnterpriseId);
            $this->stripeInvoiceImportService->importPaidUnlinkedSyncsForEnterprise($teamId, $selectedEnterpriseId);

            $metadataLinkedPayment = $this->importService->importFromExistingStripeMetadataLinks($sync);
            if ($metadataLinkedPayment instanceof Payment)
            {
                return $this->redirectAfterMetadataBackfill($sync);
            }

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
                preferPaidNear: $sync->charge_created_at,
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

        $payment = $this->importService->importFromExistingStripeMetadataLinks($sync);
        if ($payment instanceof Payment && $payment->invoice_id)
        {
            return redirect()->route('invoice.show', $payment->invoice_id);
        }

        $invoice = $this->importService->materializeLinkedStripeInvoices($sync)->first();
        if (! $invoice instanceof Invoice)
        {
            return redirect()
                ->route('payments.syncs.mercadopago.index')
                ->with('error', __('payment_sync.mercadopago.errors.stripe_invoice_missing'));
        }

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

    private function redirectAfterMetadataBackfill(PaymentSync $sync): RedirectResponse
    {
        $count = Payment::withoutGlobalScopes()
            ->where('team_id', $sync->team_id)
            ->where('source_provider', 'mercadopago')
            ->where(function ($query) use ($sync): void
            {
                $query->where('source_reference_id', $sync->external_id)
                    ->orWhere('source_reference_id', 'like', $sync->external_id.':%');
            })
            ->count() ?: 1;

        return redirect()
            ->route('payments.syncs.mercadopago.index')
            ->with('success', __('payment_sync.mercadopago.success_metadata_backfill', [
                'reference' => $sync->external_id,
                'count' => $count,
            ]));
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
