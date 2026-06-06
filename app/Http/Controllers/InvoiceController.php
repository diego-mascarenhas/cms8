<?php

namespace App\Http\Controllers;

use App\DataTables\InvoiceDataTable;
use App\Http\Requests\StoreInvoiceCreditNoteRequest;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Models\Enterprise;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Services\Billing\StripeInvoiceCreditNoteService;
use App\Services\Finance\InvoiceCreditNoteService;
use App\Services\Finance\InvoiceDisplayLineItemService;
use App\Services\Finance\InvoicePaymentDetailService;
use App\Services\Finance\InvoicePaymentRegistrationService;
use App\Services\Finance\InvoiceSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicePaymentRegistrationService $invoicePaymentRegistrationService,
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

        return $dataTable->render('invoice.index', compact(
            'exchangeRates',
            'lastUpdate',
            'invoiceStats',
            'initialSummaryFilter',
        ));
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
        ])->findOrFail($id);

        $this->authorize('view', $invoice);

        $paymentDetails = app(InvoicePaymentDetailService::class)->forInvoice($invoice);
        $displayLineItems = app(InvoiceDisplayLineItemService::class)->forInvoice($invoice);
        $canRegisterPayment = $this->invoicePaymentRegistrationService->canRegisterPayment(auth()->user(), $invoice);
        $paymentFormDefaults = $canRegisterPayment
            ? $this->invoicePaymentRegistrationService->formDefaults($invoice)
            : null;
        $canShowCreditNoteForm = $this->invoiceCreditNoteService->canShowCreditNoteForm(auth()->user(), $invoice);
        $canIssueCreditNote = $this->invoiceCreditNoteService->canIssueCreditNote(auth()->user(), $invoice);
        $creditNoteReasons = InvoiceCreditNoteService::STRIPE_REASONS;
        $defaultCreditNoteReason = $this->invoiceCreditNoteService->defaultReason();

        return view('invoices.show', compact(
            'invoice',
            'paymentDetails',
            'displayLineItems',
            'canRegisterPayment',
            'paymentFormDefaults',
            'canShowCreditNoteForm',
            'canIssueCreditNote',
            'creditNoteReasons',
            'defaultCreditNoteReason',
        ));
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
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()
            ->route('invoice.show', $invoice->id)
            ->with('success', __('invoice_payment.success'));
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
