<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptProjectBudgetPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accepted_by_name' => 'nullable|string|max:255',
            'accept_debit' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('accept_debit'))
        {
            $this->merge(['accept_debit' => false]);
        }
    }
}
