<?php

namespace App\Http\Requests;

use App\Enums\TeamBillingFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'invoice_frequency' => ['required', Rule::enum(TeamBillingFrequency::class)],
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
            'invoice_frequency.required' => 'La frecuencia de facturación es obligatoria.',
            'invoice_frequency.Illuminate\Validation\Rules\Enum' => 'La frecuencia de facturación no es válida.',
        ];
    }
}
