<?php

namespace App\Http\Requests;

use App\Models\Enterprise;
use App\Models\Invoice;
use App\Models\PaymentSync;
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
                    fn ($query) => $query->where('team_id', $teamId)->where('type_id', 1),
                ),
            ],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => [
                'integer',
                Rule::exists('invoices', 'id')->where(
                    fn ($query) => $query->where('team_id', $teamId)->where('operation', 'sell'),
                ),
            ],
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
                $currency = strtoupper((string) $sync->currency);
                $cents = (int) $sync->amount_net_cents;
                $paymentAmount = in_array($currency, ['CLP', 'UYU', 'PYG'], true)
                    ? (float) $cents
                    : round($cents / 100, 2);
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
}
