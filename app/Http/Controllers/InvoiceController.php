<?php

namespace App\Http\Controllers;

use App\DataTables\InvoiceDataTable;
use App\Http\Requests\StoreInvoiceCreditNoteRequest;
use App\Http\Requests\StoreInvoiceElectronicPaymentRequest;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\FiscalExport;
use App\Models\Invoice;
use App\Services\Billing\InvoiceInboundSyncService;
use App\Services\Billing\StripeInvoiceCreditNoteService;
use App\Services\Finance\InvoiceCreditNoteService;
use App\Services\Finance\InvoiceDisplayLineItemService;
use App\Services\Finance\InvoiceElectronicPaymentLinkService;
use App\Services\Finance\InvoicePaymentDetailService;
use App\Services\Finance\InvoicePaymentRegistrationService;
use App\Services\Finance\InvoiceSummaryService;
use App\Services\Finance\PaymentStatusUpdateService;
use App\Services\Fiscal\Exceptions\FiscalExportException;
use App\Services\Fiscal\FiscalExportRouter;
use App\Services\Fiscal\FiscalExportService;
use App\Services\Fiscal\NullFiscalExportAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicePaymentRegistrationService $invoicePaymentRegistrationService,
        private readonly InvoiceElectronicPaymentLinkService $invoiceElectronicPaymentLinkService,
        private readonly InvoiceCreditNoteService $invoiceCreditNoteService,
        private readonly StripeInvoiceCreditNoteService $stripeInvoiceCreditNoteService,
    ) {
        // Note: Manual authorization in each method due to non-standard route parameter names
        // Laravel's authorizeResource() expects {invoice} parameter, but routes use {id}
    }

    public function index(InvoiceDataTable $dataTable)
    {
        $this->authorize('viewAny', Invoice::class);

        // Obtener tipos de cambio actuales
        $exchangeRates = [
            'USD_ARS' => ExchangeRate::getLatestRate('USD', 'ARS'),
            'USD_EUR' => ExchangeRate::getLatestRate('USD', 'EUR'),
            'ARS_EUR' => ExchangeRate::getLatestRate('ARS', 'EUR'),
        ];

        // Obtener fecha de última actualización
        $lastUpdate = ExchangeRate::latest('fetched_at')->first();

        $invoiceStats = app(InvoiceSummaryService::class)->buildIndexStats(
            (int) auth()->user()->currentTeam->id,
        );

        $initialSummaryFilter = app(InvoiceSummaryService::class)->resolveListFilter(
            request()->query('summary_filter'),
        );

        $team = auth()->user()->currentTeam;
        $inboundSyncService = app(InvoiceInboundSyncService::class);
        $invoiceSyncProviders = $team ? $inboundSyncService->availableProviders($team) : [];

        return $dataTable->render('invoice.index', compact(
            'exchangeRates',
            'lastUpdate',
            'invoiceStats',
            'initialSummaryFilter',
            'invoiceSyncProviders',
        ));
    }

    /**
     * Pull invoices from configured inbound providers (Stripe, Cuéntica) for the current team.
     */
    public function syncInbound(InvoiceInboundSyncService $inboundSyncService): RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $team = auth()->user()->currentTeam;
        if (! $team)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_sync.errors.no_team'));
        }

        if (! $inboundSyncService->canSync($team))
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_sync.errors.nothing_configured'));
        }

        $result = $inboundSyncService->syncForTeam($team);

        if ($result['imported'] === 0 && $result['updated'] === 0 && $result['skipped'] > 0)
        {
            return redirect()->route('invoice.index')->with(
                'warning',
                __('invoice_sync.sync_warning_skipped', [
                    'providers' => $inboundSyncService->providerLabels($result['providers']),
                    'skipped' => $result['skipped'],
                ]),
            );
        }

        return redirect()->route('invoice.index')->with(
            'success',
            __('invoice_sync.sync_success', [
                'providers' => $inboundSyncService->providerLabels($result['providers']),
                'imported' => $result['imported'],
                'updated' => $result['updated'],
            ]),
        );
    }

    /**
     * Form: set enterprise (client) for an invoice that has no company yet.
     */
    public function linkEnterpriseForm(Invoice $invoice): View|RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $this->denyIfCannotLinkEnterprise();

        if ($invoice->enterprise_id)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_enterprise.link.errors.already_linked'));
        }

        $enterprises = $this->enterprisesForLinking();

        return view('invoice.link-enterprise', [
            'invoice' => $invoice->loadMissing(['type']),
            'enterprises' => $enterprises,
        ]);
    }

    public function linkEnterprise(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $this->denyIfCannotLinkEnterprise();

        if ($invoice->enterprise_id)
        {
            return redirect()->route('invoice.index')->with('error', __('invoice_enterprise.link.errors.already_linked'));
        }

        $teamId = (int) auth()->user()->currentTeam->id;
        $validated = $request->validate([
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')
                    ->where(fn ($q) => $q
                        ->where('team_id', $teamId)
                        ->where('type_id', 1)
                        ->whereNull('deleted_at')),
            ],
        ]);

        $enterprise = Enterprise::query()->findOrFail($validated['enterprise_id']);
        $this->authorize('update', $enterprise);
        $this->assertCollaboratorOwnsEnterprise($enterprise);

        $invoice->update([
            'enterprise_id' => $enterprise->id,
        ]);

        return redirect()->route('invoice.index')->with('success', __('invoice_enterprise.link.success'));
    }

    private function denyIfCannotLinkEnterprise(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'collaborator']))
        {
            abort(403);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Enterprise>
     */
    private function enterprisesForLinking()
    {
        $user = auth()->user();
        $query = Enterprise::query()
            ->clients()
            ->orderBy('name');

        if ($user->hasRole('admin'))
        {
            return $query->get();
        }

        return $query->where('responsible_id', $user->id)->get();
    }

    private function assertCollaboratorOwnsEnterprise(Enterprise $enterprise): void
    {
        $user = auth()->user();
        if ($user->hasRole('admin'))
        {
            return;
        }

        if ((int) $enterprise->getAttribute('responsible_id') !== (int) $user->id)
        {
            abort(403);
        }
    }

    public function show($id): View
    {
        $invoice = Invoice::with([
            'enterprise',
            'items.category',
            'type',
            'stripeInvoiceSync',
            'fiscalExports',
        ])->findOrFail($id);

        $this->authorize('view', $invoice);

        $fiscalPlatform = app(FiscalExportRouter::class)->resolvePlatform($invoice);
        if ($fiscalPlatform === NullFiscalExportAdapter::PLATFORM)
        {
            $fiscalPlatform = null;
        }
        $fiscalExport = $fiscalPlatform
            ? $invoice->fiscalExports->firstWhere('platform', $fiscalPlatform)
            : null;
        $canExportFiscal = auth()->user()->can('invoice.edit')
            && app(FiscalExportService::class)->isEligible($invoice);

        $paymentDetails = app(InvoicePaymentDetailService::class)->forInvoice($invoice);
        $displayLineItems = app(InvoiceDisplayLineItemService::class)->forInvoice($invoice);
        $canLinkElectronicPayment = $this->invoiceElectronicPaymentLinkService->canLink(auth()->user(), $invoice);
        $electronicPaymentSyncOptions = $canLinkElectronicPayment
            ? $this->invoiceElectronicPaymentLinkService->syncOptions($invoice)
            : [];
        $canShowCreditNoteForm = $this->invoiceCreditNoteService->canShowCreditNoteForm(auth()->user(), $invoice);
        $canIssueCreditNote = $this->invoiceCreditNoteService->canIssueCreditNote(auth()->user(), $invoice);
        $creditNoteReasons = InvoiceCreditNoteService::STRIPE_REASONS;
        $defaultCreditNoteReason = $this->invoiceCreditNoteService->defaultReason();
        $canUpdatePaymentStatus = auth()->user()->currentTeam
            && (int) $invoice->team_id === (int) auth()->user()->currentTeam->id
            && auth()->user()->ownsTeam(auth()->user()->currentTeam);
        $paymentStatusOptions = app(PaymentStatusUpdateService::class)->selectableStatuses();
        $originalInvoice = $invoice->isCreditNote() ? $invoice->originalInvoice() : null;
        $existingCreditNote = (! $invoice->isCreditNote()) ? $invoice->existingCreditNote() : null;

        return view('invoices.show', compact(
            'invoice',
            'paymentDetails',
            'displayLineItems',
            'canLinkElectronicPayment',
            'electronicPaymentSyncOptions',
            'canShowCreditNoteForm',
            'canIssueCreditNote',
            'creditNoteReasons',
            'defaultCreditNoteReason',
            'canUpdatePaymentStatus',
            'paymentStatusOptions',
            'fiscalPlatform',
            'fiscalExport',
            'canExportFiscal',
            'originalInvoice',
            'existingCreditNote',
        ));
    }

    public function exportFiscal(Invoice $invoice, FiscalExportService $service): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! auth()->user()->can('invoice.edit'))
        {
            abort(403);
        }

        try
        {
            $export = $service->export($invoice, force: request()->boolean('force'));
        } catch (FiscalExportException $exception)
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('Error al exportar la factura: :message', ['message' => $exception->getMessage()]));
        }

        if (! $export instanceof FiscalExport)
        {
            return redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('La exportación fiscal está deshabilitada.'));
        }

        return match ($export->status)
        {
            FiscalExport::STATUS_EXPORTED, FiscalExport::STATUS_RECTIFIED => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('success', __('Factura exportada a :platform (Nº :number).', [
                    'platform' => ucfirst($export->platform),
                    'number' => $export->external_number ?: $export->external_id,
                ])),
            FiscalExport::STATUS_SKIPPED => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('No se exportó: :message', ['message' => $export->error_message])),
            default => redirect()
                ->route('invoice.show', $invoice->id)
                ->with('error', __('Error al exportar la factura: :message', ['message' => $export->error_message])),
        };
    }

    public function storePayment(StoreInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoicePaymentRegistrationService->canRegisterPayment($request->user(), $invoice))
        {
            abort(403);
        }

        try
        {
            $this->invoicePaymentRegistrationService->register($request->user(), $invoice, $request->validated());
        } catch (ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_payment.success'));
    }

    public function storeElectronicPayment(StoreInvoiceElectronicPaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoiceElectronicPaymentLinkService->canLink($request->user(), $invoice))
        {
            abort(403);
        }

        try
        {
            $this->invoiceElectronicPaymentLinkService->link(
                $request->user(),
                $invoice,
                $request->paymentSync(),
            );
        } catch (ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_payment.electronic_success'));
    }

    public function storeCreditNote(StoreInvoiceCreditNoteRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('view', $invoice);

        if (! $this->invoiceCreditNoteService->canIssueCreditNote($request->user(), $invoice))
        {
            abort(403, __('invoice_credit_note.errors.not_allowed'));
        }

        try
        {
            $result = $this->stripeInvoiceCreditNoteService->issueForInvoice(
                $invoice,
                (string) $request->validated('reason'),
            );
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        $reference = $result['number'] ?? $result['credit_note_id'];

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_credit_note.success', ['reference' => $reference]));
    }
}
