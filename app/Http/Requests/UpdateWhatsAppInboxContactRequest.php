<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppInboxContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('email'))
        {
            return;
        }

        $email = trim((string) $this->input('email', ''));
        $this->merge([
            'email' => $email !== '' ? $email : null,
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required_without:contact_id', 'nullable', 'string'],
            'contact_id' => ['required_without:phone', 'nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
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
            'phone.required_without' => __('Invalid phone number.'),
            'contact_id.required_without' => __('No CRM contact is linked to this number. Create or link a contact in Humano to use this option.'),
            'name.required' => __('El nombre es obligatorio.'),
            'email.email' => __('Introduce un email válido.'),
            'status_id.required' => __('The selected status is invalid.'),
            'status_id.exists' => __('The selected status is invalid.'),
        ];
    }
}
