<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReformulateProjectBudgetPreviewRequest extends FormRequest
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
            'name' => 'nullable|string|max:255',
            'message' => 'required|string|min:10|max:5000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => __('Please describe what you would like to change in the quote.'),
            'message.min' => __('Please write at least :min characters.', ['min' => 10]),
        ];
    }
}
