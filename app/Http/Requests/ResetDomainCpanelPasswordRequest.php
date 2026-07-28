<?php

namespace App\Http\Requests;

use App\Services\Hosting\DomainCpanelPasswordService;
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
            'notify_to' => [
                Rule::requiredIf(in_array($channel, ['whatsapp', 'email'], true)),
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($channel): void
                {
                    if (! in_array($channel, ['whatsapp', 'email'], true) || $value === null || $value === '')
                    {
                        return;
                    }

                    $value = (string) $value;

                    if ($value === DomainCpanelPasswordService::NOTIFY_TO_HOSTING)
                    {
                        if ($channel !== 'email')
                        {
                            $fail('El email del plan de hosting solo se puede usar con el canal email.');
                        }

                        return;
                    }

                    if (! ctype_digit($value))
                    {
                        $fail('El destinatario seleccionado no es válido.');
                    }
                },
            ],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
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
            'notify_to.required' => 'Seleccioná el destinatario al que se enviarán los datos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('notify_channel'))
        {
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

        if (! $this->filled('notify_to') && $this->filled('contact_id'))
        {
            $this->merge([
                'notify_to' => (string) $this->input('contact_id'),
            ]);
        }
    }
}
