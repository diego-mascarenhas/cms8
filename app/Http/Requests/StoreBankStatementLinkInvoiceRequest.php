<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBankStatementLinkInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('paymentAccount');

        return $this->user()?->can('update', $account) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $invoiceIds = $this->input('invoice_ids');

        if (! is_array($invoiceIds) && $this->filled('invoice_id'))
        {
            $invoiceIds = [$this->input('invoice_id')];
        }

        if (is_array($invoiceIds))
        {
            $normalized = array_values(array_unique(array_filter(
                array_map('intval', $invoiceIds),
                fn (int $id): bool => $id > 0,
            )));

            $this->merge([
                'invoice_ids' => $normalized,
                'invoice_id' => $normalized[0] ?? null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'type_id' => ['nullable', 'integer', 'exists:payment_types,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            if (! is_array($this->input('invoice_ids')) || $this->input('invoice_ids') === [])
            {
                $validator->errors()->add('invoice_ids', __('Elegí una factura.'));
            }
        });
    }

    /**
     * @return list<int>
     */
    public function invoiceIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('invoice_ids')));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_ids.required' => __('Elegí una factura.'),
            'invoice_ids.min' => __('Elegí una factura.'),
            'invoice_ids.*.exists' => __('La factura no existe.'),
            'invoice_id.exists' => __('La factura no existe.'),
            'type_id.exists' => __('La forma de pago no existe.'),
        ];
    }
}
