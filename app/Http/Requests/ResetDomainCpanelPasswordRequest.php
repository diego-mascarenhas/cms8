<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ResetDomainCpanelPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $channel = (string) $this->input('notify_channel', 'none');

        return [
            'password' => ['nullable', 'string', 'max:255', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'notify_channel' => ['required', Rule::in(['none', 'whatsapp', 'email'])],
            'contact_id' => [
                Rule::requiredIf(in_array($channel, ['whatsapp', 'email'], true)),
                'nullable',
                'integer',
                'exists:contacts,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'notify_channel.required' => 'Indicá cómo querés enviar los datos de acceso.',
            'notify_channel.in' => 'El canal de envío no es válido.',
            'contact_id.required' => 'Seleccioná el contacto al que se enviarán los datos.',
            'contact_id.exists' => 'El contacto seleccionado no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('notify_channel'))
        {
            // Backward compatibility with the previous checkbox.
            if ($this->has('send_whatsapp'))
            {
                $this->merge([
                    'notify_channel' => $this->boolean('send_whatsapp') ? 'whatsapp' : 'none',
                ]);
            } else
            {
                $this->merge(['notify_channel' => 'whatsapp']);
            }
        }
    }
}
