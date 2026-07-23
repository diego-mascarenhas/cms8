<?php

namespace App\Http\Requests;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\PaymentSync;
use App\Support\MercadoPagoPaidInvoiceLinker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportMercadoPagoPaymentSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Payment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) $this->user()->currentTeam->id;

        return [
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')->where(
                    fn ($query) => $query->where('team_id', $teamId),
                ),
            ],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => [
                'integer',
                Rule::exists('invoices', 'id')->where(
                    fn ($query) => $query->where('team_id', $teamId)->where('operation', 'sell'),
                ),
            ],
            'remarks' => ['nullable', 'string', 'max:500'],
            'link_payer_code' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $enterpriseId = (int) $this->input('enterprise_id');
            $invoiceIds = array_values(array_unique(array_map('intval', (array) $this->input('invoice_ids', []))));

            if ($enterpriseId <= 0 || $invoiceIds === [])
            {
                return;
            }

            $teamId = (int) $this->user()->currentTeam->id;
            $invoices = Invoice::withoutGlobalScopes()
                ->where('team_id', $teamId)
                ->whereIn('id', $invoiceIds)
                ->get();

            if ($invoices->count() !== count($invoiceIds))
            {
                $validator->errors()->add('invoice_ids', __('payment_sync.mercadopago.errors.invoice_invalid'));

                return;
            }

            foreach ($invoices as $invoice)
            {
                if ((int) $invoice->enterprise_id !== $enterpriseId)
                {
                    $validator->errors()->add(
                        'invoice_ids',
                        __('payment_sync.mercadopago.errors.invoice_enterprise_mismatch'),
                    );

                    return;
                }
            }

            $paidUnlinked = $invoices->filter(
                fn (Invoice $invoice) => MercadoPagoPaidInvoiceLinker::isPaidUnlinkedCandidate($invoice),
            );
            $open = $invoices->filter(fn (Invoice $invoice) => (float) $invoice->balance > 0);

            if ($paidUnlinked->isNotEmpty())
            {
                if ($paidUnlinked->count() !== $invoices->count() || $open->isNotEmpty())
                {
                    $validator->errors()->add(
                        'invoice_ids',
                        __('payment_sync.mercadopago.errors.paid_link_mixed'),
                    );

                    return;
                }

                /** @var PaymentSync $sync */
                $sync = $this->route('sync');
                $paymentAmount = $this->paymentAmountMajor($sync);
                $invoiceTotal = round(
                    (float) $paidUnlinked->sum(fn (Invoice $invoice) => (float) $invoice->total_amount),
                    2,
                );

                if (abs($invoiceTotal - $paymentAmount) > 0.05)
                {
                    $validator->errors()->add(
                        'invoice_ids',
                        __('payment_sync.mercadopago.errors.paid_link_amount_mismatch', [
                            'total' => number_format($invoiceTotal, 2, ',', '.'),
                            'amount' => number_format($paymentAmount, 2, ',', '.'),
                        ]),
                    );
                }

                return;
            }

            foreach ($invoices as $invoice)
            {
                if ((float) $invoice->balance <= 0)
                {
                    $validator->errors()->add(
                        'invoice_ids',
                        __('payment_sync.mercadopago.errors.invoice_no_balance'),
                    );

                    return;
                }
            }

            if (count($invoiceIds) > 1)
            {
                /** @var PaymentSync $sync */
                $sync = $this->route('sync');
                $paymentAmount = $this->paymentAmountMajor($sync);
                $sum = round((float) $invoices->sum(fn (Invoice $invoice) => (float) $invoice->balance), 2);

                if (abs($sum - $paymentAmount) > 0.05)
                {
                    $validator->errors()->add(
                        'invoice_ids',
                        __('payment_sync.mercadopago.errors.invoice_sum_mismatch', [
                            'sum' => number_format($sum, 2, ',', '.'),
                            'amount' => number_format($paymentAmount, 2, ',', '.'),
                        ]),
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'enterprise_id.required' => __('payment_sync.mercadopago.errors.enterprise_required'),
            'enterprise_id.exists' => __('payment_sync.mercadopago.errors.enterprise_invalid'),
            'invoice_ids.*.exists' => __('payment_sync.mercadopago.errors.invoice_invalid'),
        ];
    }

    /**
     * @return list<int>
     */
    public function invoiceIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) ($this->validated('invoice_ids') ?? []))));
    }

    public function enterprise(): Enterprise
    {
        return Enterprise::query()->findOrFail((int) $this->validated('enterprise_id'));
    }

    private function paymentAmountMajor(PaymentSync $sync): float
    {
        $currency = strtoupper((string) $sync->currency);
        $cents = (int) $sync->amount_net_cents;

        if (in_array($currency, ['CLP', 'UYU', 'PYG'], true))
        {
            return (float) $cents;
        }

        return round($cents / 100, 2);
    }
}
