<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateAssistantPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->currentTeam;

        return $team !== null && $user->ownsTeam($team);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
            'success_url.required' => __('Falta la URL de retorno.'),
            'success_url.url' => __('La URL de retorno no es válida.'),
            'cancel_url.required' => __('Falta la URL de cancelación.'),
            'cancel_url.url' => __('La URL de cancelación no es válida.'),
        ];
    }
}
