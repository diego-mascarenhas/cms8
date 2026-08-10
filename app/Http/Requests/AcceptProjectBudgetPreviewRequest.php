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
            'accept_deposit_terms' => 'accepted',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_deposit_terms.accepted' => __('You must confirm that the project will not start until 30% of the payment is received.'),
        ];
    }
}
