<?php

namespace App\Http\Requests;

use App\Helpers\Helpers;
use App\Support\ExpenseDocumentTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'document_type' => ['required', Rule::in(ExpenseDocumentTypes::enabledKeys())],
            'enterprise_id' => [
                'required',
                'integer',
                Rule::exists('enterprises', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)),
            ],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'expense_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'expense_category' => ['nullable', 'string', 'max:150'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.concept' => ['required', 'string', 'max:255'],
            'lines.*.base_amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.allocation_percent' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'cash_criteria' => ['sometimes', 'boolean'],
            'is_investment' => ['sometimes', 'boolean'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_date' => ['required', 'date'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'payments.*.type_id' => ['required', 'integer', 'exists:payment_types,id'],
            'payments.*.account_id' => [
                'required',
                'integer',
                Rule::exists('payment_accounts', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)
                    ->where('status', 1)),
            ],
            'payments.*.status' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'string', 'max:255'],
            'submit_action' => ['nullable', Rule::in(['draft', 'save'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);

        if (! is_array($lines))
        {
            $lines = [];
        }

        $normalizedLines = [];

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $concept = trim((string) ($line['concept'] ?? ''));
            $baseAmount = $line['base_amount'] ?? null;

            if ($concept === '' && blank($baseAmount))
            {
                continue;
            }

            $normalizedLines[] = [
                'concept' => $concept,
                'base_amount' => Helpers::parseDecimalInput($baseAmount) ?? $baseAmount,
                'vat_percent' => Helpers::parseDecimalInput($line['vat_percent'] ?? 0) ?? ($line['vat_percent'] ?? 0),
                'retention_percent' => Helpers::parseDecimalInput($line['retention_percent'] ?? 0) ?? ($line['retention_percent'] ?? 0),
                'allocation_percent' => Helpers::parseDecimalInput($line['allocation_percent'] ?? 100) ?? ($line['allocation_percent'] ?? 100),
            ];
        }

        $this->merge([
            'document_type' => (string) $this->input('document_type', 'invoice'),
            'due_date' => $this->input('due_date', $this->input('date')),
            'lines' => $normalizedLines,
            'payments' => $this->normalizePayments(),
            'cash_criteria' => $this->boolean('cash_criteria'),
            'is_investment' => $this->boolean('is_investment'),
            'submit_action' => (string) $this->input('submit_action', 'save'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayments(): array
    {
        $payments = $this->input('payments', []);

        if (! is_array($payments) || $payments === [])
        {
            if ($this->filled('payment_date'))
            {
                return [[
                    'payment_date' => $this->input('payment_date'),
                    'amount' => $this->input('payment_amount'),
                    'type_id' => $this->input('type_id'),
                    'account_id' => $this->input('account_id'),
                    'status' => $this->input('status', 2),
                ]];
            }

            return [];
        }

        $normalizedPayments = [];

        foreach ($payments as $payment)
        {
            if (! is_array($payment))
            {
                continue;
            }

            $normalizedPayments[] = [
                'payment_date' => $payment['payment_date'] ?? null,
                'amount' => filled($payment['amount'] ?? null)
                    ? (Helpers::parseDecimalInput($payment['amount']) ?? $payment['amount'])
                    : null,
                'type_id' => $payment['type_id'] ?? null,
                'account_id' => $payment['account_id'] ?? null,
                'status' => $payment['status'] ?? 2,
            ];
        }

        return $normalizedPayments;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            if ($validator->errors()->isNotEmpty())
            {
                return;
            }

            $lines = $this->input('lines', []);
            $invoiceTotal = $this->calculateInvoiceTotal(is_array($lines) ? $lines : []);
            $payments = $this->input('payments', []);

            if (! is_array($payments))
            {
                return;
            }

            $specifiedSum = 0.0;
            $hasSpecifiedAmount = false;

            foreach ($payments as $payment)
            {
                if (! is_array($payment) || ! filled($payment['amount'] ?? null))
                {
                    continue;
                }

                $hasSpecifiedAmount = true;
                $specifiedSum += round((float) $payment['amount'], 2);
            }

            if ($hasSpecifiedAmount && $specifiedSum > round($invoiceTotal + 0.001, 2))
            {
                $validator->errors()->add(
                    'payments.0.amount',
                    'La suma de los importes de pago no puede superar el total del gasto ('.Helpers::formatDecimal($invoiceTotal).').',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'enterprise_id' => 'proveedor',
            'document_file' => 'documento',
            'date' => 'fecha',
            'due_date' => 'fecha de vencimiento',
            'document_number' => 'número de comprobante',
            'expense_category_id' => 'tipo de gasto',
            'currency_id' => 'moneda',
            'lines' => 'líneas',
            'lines.*.concept' => 'concepto',
            'lines.*.base_amount' => 'importe',
            'lines.*.vat_percent' => 'IVA',
            'lines.*.retention_percent' => 'retención',
            'lines.*.allocation_percent' => 'imputación',
            'payments' => 'pagos',
            'payments.*.payment_date' => 'fecha del pago',
            'payments.*.amount' => 'importe del pago',
            'payments.*.type_id' => 'forma de pago',
            'payments.*.account_id' => 'cuenta',
            'payments.*.status' => 'estado del pago',
            'remarks' => 'comentario',
            'tags' => 'etiquetas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Debes añadir al menos una línea al gasto.',
            'lines.min' => 'Debes añadir al menos una línea al gasto.',
            'payments.required' => 'Debes añadir al menos un pago.',
            'payments.min' => 'Debes añadir al menos un pago.',
            'enterprise_id.exists' => 'El proveedor seleccionado no es válido.',
            'payments.*.account_id.exists' => 'La cuenta seleccionada no es válida.',
            'lines.*.base_amount.required' => 'Importe obligatorio',
            'lines.*.base_amount.numeric' => 'Importe obligatorio',
            'lines.*.base_amount.min' => 'Importe obligatorio',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function calculateInvoiceTotal(array $lines): float
    {
        $total = 0.0;

        foreach ($lines as $line)
        {
            if (! is_array($line))
            {
                continue;
            }

            $baseAmount = round((float) ($line['base_amount'] ?? 0), 2);
            $vatPercent = (float) ($line['vat_percent'] ?? 0);
            $retentionPercent = (float) ($line['retention_percent'] ?? 0);
            $allocationPercent = (float) ($line['allocation_percent'] ?? 100);

            $vatAmount = round($baseAmount * ($vatPercent / 100), 2);
            $retentionAmount = round($baseAmount * ($retentionPercent / 100), 2);
            $lineTotal = round($baseAmount + $vatAmount - $retentionAmount, 2);
            $total += round($lineTotal * ($allocationPercent / 100), 2);
        }

        return round(max($total, 0), 2);
    }
}
