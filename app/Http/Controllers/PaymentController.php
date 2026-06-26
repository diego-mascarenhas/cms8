<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentDataTable;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\PaymentInvoiceLinkService;
use App\Services\Finance\PaymentStatusUpdateService;
use App\Services\Finance\PaymentSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentInvoiceLinkService $paymentInvoiceLinkService,
        private readonly PaymentSummaryService $paymentSummaryService,
        private readonly PaymentStatusUpdateService $paymentStatusUpdateService,
    ) {}

    public function index(PaymentDataTable $dataTable)
    {
        $this->authorize('viewAny', Payment::class);

        $paymentSummary = $this->paymentSummaryService->forTeam(auth()->user()->currentTeam);

        return $dataTable->render('payments.index', compact('paymentSummary'));
    }

    public function linkInvoiceForm(Payment $payment): View|RedirectResponse
    {
        $this->denyIfCannotLinkInvoice();
        if ($payment->invoice_id)
        {
            return redirect()->route('payments.index')->with('error', __('payment_invoice.link.errors.already_linked'));
        }

        $invoices = $this->paymentInvoiceLinkService->invoicesForPayment($payment);

        return view('payments.link-invoice', [
            'payment' => $payment->loadMissing(['enterprise', 'account', 'type']),
            'invoices' => $invoices,
        ]);
    }

    public function linkInvoice(Request $request, Payment $payment): RedirectResponse
    {
        $this->denyIfCannotLinkInvoice();
        if ($payment->invoice_id)
        {
            return redirect()->route('payments.index')->with('error', __('payment_invoice.link.errors.already_linked'));
        }

        $teamId = (int) auth()->user()->currentTeam->id;

        $validated = $request->validate([
            'invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where(fn ($q) => $q->where('team_id', $teamId)),
            ],
        ]);

        $invoice = Invoice::query()->findOrFail($validated['invoice_id']);
        $this->authorize('view', $invoice);

        try
        {
            $this->paymentInvoiceLinkService->linkPaymentToInvoice($payment, $invoice);
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('payments.index')->with('success', __('payment_invoice.link.success'));
    }

    private function denyIfCannotLinkInvoice(): void
    {
        $this->authorize('create', Payment::class);
    }

    public function show($id): View
    {
        $payment = Payment::with(['enterprise', 'invoice', 'account', 'type'])->findOrFail($id);
        $this->authorize('view', $payment);

        return view('payments.show', compact('payment'));
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment): RedirectResponse
    {
        if (! $this->paymentStatusUpdateService->canUpdateStatus($request->user(), $payment))
        {
            abort(403, __('payment_status.errors.not_allowed'));
        }

        try
        {
            $this->paymentStatusUpdateService->update(
                $request->user(),
                $payment,
                (int) $request->validated('status'),
            );
        } catch (\Illuminate\Validation\ValidationException $exception)
        {
            return back()->withInput()->withErrors($exception->errors());
        }

        return back()->with('success', __('payment_status.success'));
    }
}
