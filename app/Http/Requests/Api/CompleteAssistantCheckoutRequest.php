<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAssistantCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.required' => __('Falta el identificador de la sesión de Stripe.'),
        ];
    }
}
