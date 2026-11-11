<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHostingEmailQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canAccessInfrastructure();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'unlimited' => $this->boolean('unlimited'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'quota_mb' => ['required_unless:unlimited,true', 'nullable', 'integer', 'min:1', 'max:1048576'],
            'unlimited' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indicá la cuenta de correo.',
            'quota_mb.required_unless' => 'Indicá la nueva cuota.',
            'quota_mb.integer' => 'La cuota tiene que ser un número entero de MB.',
            'quota_mb.min' => 'La cuota tiene que ser de al menos 1 MB.',
        ];
    }
}
