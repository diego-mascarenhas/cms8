<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAssistantCheckoutRequest extends FormRequest
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
            'interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'success_url' => ['required', 'url', 'max:2048'],
            'cancel_url' => ['required', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interval.required' => __('Elegí si el plan es mensual o anual.'),
            'interval.in' => __('El intervalo debe ser mensual o anual.'),
            'success_url.required' => __('Falta la URL de retorno.'),
            'success_url.url' => __('La URL de retorno no es válida.'),
            'cancel_url.required' => __('Falta la URL de cancelación.'),
            'cancel_url.url' => __('La URL de cancelación no es válida.'),
        ];
    }
}
