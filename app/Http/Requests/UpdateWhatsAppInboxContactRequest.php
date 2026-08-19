<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppInboxContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'status_id' => ['required', 'integer', 'exists:contact_statuses,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => __('Invalid phone number.'),
            'name.required' => __('El nombre es obligatorio.'),
            'status_id.required' => __('The selected status is invalid.'),
            'status_id.exists' => __('The selected status is invalid.'),
        ];
    }
}
