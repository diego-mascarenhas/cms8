<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'document_type' => ['required', Rule::in([
                'invoice',
                'receipt',
                'tax',
                'depreciation',
                'dividend',
                'payroll',
                'loan',
            ])],
            'enterprise_id' => [
                'nullable',
                'integer',
                Rule::exists('enterprises', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)),
            ],
            'document_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'date' => ['required', 'date'],
            'document_number' => ['nullable', 'string', 'max:120'],
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
            'payment_date' => ['required', 'date'],
            'payment_amount' => ['nullable', 'numeric', 'min:0.01'],
            'type_id' => ['required', 'integer', 'exists:payment_types,id'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('payment_accounts', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)
                    ->where('status', 1)),
            ],
            'status' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
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
                'base_amount' => $baseAmount,
                'vat_percent' => $line['vat_percent'] ?? 0,
                'retention_percent' => $line['retention_percent'] ?? 0,
                'allocation_percent' => $line['allocation_percent'] ?? 100,
            ];
        }

        $this->merge([
            'document_type' => (string) $this->input('document_type', 'invoice'),
            'lines' => $normalizedLines,
            'cash_criteria' => $this->boolean('cash_criteria'),
            'is_investment' => $this->boolean('is_investment'),
            'submit_action' => (string) $this->input('submit_action', 'save'),
        ]);
    }
}
