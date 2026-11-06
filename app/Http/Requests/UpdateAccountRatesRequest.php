<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('root') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tokens_multiplier' => ['required', 'numeric', 'min:1', 'max:1000'],
            'whatsapp_send' => ['required', 'numeric', 'min:0', 'max:10'],
            'mailer_send' => ['required', 'numeric', 'min:0', 'max:10'],
            'prospect_credit' => ['required', 'numeric', 'min:0', 'max:100'],
            'storage_megabyte' => ['required', 'numeric', 'min:0', 'max:1'],
            'transcription_minute' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tokens_multiplier.required' => 'El multiplicador de tokens es obligatorio.',
            'tokens_multiplier.numeric' => 'El multiplicador de tokens debe ser numérico.',
            'tokens_multiplier.min' => 'El multiplicador de tokens debe ser al menos 1.',
            'whatsapp_send.required' => 'El precio de WhatsApp es obligatorio.',
            'whatsapp_send.numeric' => 'El precio de WhatsApp debe ser numérico.',
            'mailer_send.required' => 'El precio de mail es obligatorio.',
            'mailer_send.numeric' => 'El precio de mail debe ser numérico.',
            'prospect_credit.required' => 'El precio de prospección es obligatorio.',
            'prospect_credit.numeric' => 'El precio de prospección debe ser numérico.',
            'storage_megabyte.required' => 'El precio de almacenamiento es obligatorio.',
            'storage_megabyte.numeric' => 'El precio de almacenamiento debe ser numérico.',
            'transcription_minute.required' => 'El precio de transcripción es obligatorio.',
            'transcription_minute.numeric' => 'El precio de transcripción debe ser numérico.',
        ];
    }
}
