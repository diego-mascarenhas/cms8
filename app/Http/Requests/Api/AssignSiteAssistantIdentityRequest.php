<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignSiteAssistantIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('email'))
        {
            $email = trim((string) $this->input('email', ''));
            $this->merge([
                'email' => $email !== '' ? $email : null,
            ]);
        }

        if ($this->exists('name'))
        {
            $this->merge([
                'name' => trim((string) $this->input('name', '')),
            ]);
        }

        if ($this->exists('phone'))
        {
            $this->merge([
                'phone' => trim((string) $this->input('phone', '')),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'create' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status_id' => ['sometimes', 'nullable', 'integer', 'exists:contact_statuses,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'min:1'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'send_access' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.integer' => __('Choose a contact to link.'),
            'email.email' => __('Introduce un email válido.'),
            'password.min' => __('La contraseña debe tener al menos 8 caracteres.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void
        {
            $contactId = (int) $this->input('contact_id', 0);
            if ($contactId < 1 && ! $this->boolean('create'))
            {
                $validator->errors()->add('contact_id', __('Choose a contact or create a lead.'));
            }
        });
    }
}
