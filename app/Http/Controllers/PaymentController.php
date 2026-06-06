<?php

namespace App\Http\Controllers;

use App\DataTables\PaymentDataTable;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\PaymentInvoiceLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentInvoiceLinkService $paymentInvoiceLinkService,
    ) {}

    public function index(PaymentDataTable $dataTable)
    {
        return $dataTable->render('payments.index');
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
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'collaborator']))
        {
            abort(403);
        }
    }

    public function show($id): View
    {
        $payment = Payment::with(['enterprise', 'invoice', 'account', 'type'])->findOrFail($id);

        return view('payments.show', compact('payment'));
    }
}
