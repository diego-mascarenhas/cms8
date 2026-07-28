<?php

namespace App\Http\Requests;

use App\Services\Hosting\DomainCpanelPasswordService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateDomainEmailPasswordRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'notify_channel' => ['required', Rule::in(['none', 'whatsapp', 'email'])],
            'form_context' => ['nullable', 'string', 'in:mailbox_password'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indica la cuenta de correo.',
            'email.email' => 'La cuenta de correo no es válida.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.symbols' => 'La contraseña debe incluir al menos un símbolo.',
            'notify_channel.required' => 'Indicá cómo querés enviar los datos de acceso.',
            'notify_channel.in' => 'El canal de envío no es válido.',
            'notify_to.required' => 'Seleccioná el destinatario al que se enviarán los datos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'cuenta de correo',
            'password' => 'contraseña',
            'notify_channel' => 'canal de envío',
            'notify_to' => 'destinatario',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('notify_channel'))
        {
            $this->merge(['notify_channel' => 'none']);
        }
    }
}
