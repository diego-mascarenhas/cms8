<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectBalanceInvoiceRequest extends FormRequest
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
            'billing_mode' => 'required|in:total,installments',
            'description' => 'required|string|min:3|max:2000',
            'installments' => 'required_if:billing_mode,installments|integer|min:1|max:12',
            'start_date' => 'required_if:billing_mode,installments|date|after_or_equal:today',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => __('Enter the invoice line description.'),
            'installments.required_if' => __('Enter how many payments to create.'),
            'start_date.required_if' => __('Choose the date of the first payment.'),
            'start_date.after_or_equal' => __('The first payment cannot start in the past.'),
        ];
    }
}
