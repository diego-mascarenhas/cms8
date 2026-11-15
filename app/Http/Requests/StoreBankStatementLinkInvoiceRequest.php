<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankStatementLinkInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('paymentAccount');

        return $this->user()?->can('update', $account) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => __('Elegí una factura.'),
            'invoice_id.exists' => __('La factura no existe.'),
            'type_id.exists' => __('La forma de pago no existe.'),
        ];
    }
}
