<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreHostingEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canAccessInfrastructure();
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('email', '')));

        if (str_contains($email, '@'))
        {
            $email = explode('@', $email, 2)[0];
        }

        $this->merge([
            'email' => $email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ['required', 'string', 'max:255', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indicá el nombre de la cuenta de correo.',
            'email.max' => 'El nombre de la cuenta no puede superar :max caracteres.',
            'email.regex' => 'El nombre solo puede contener letras, números, puntos, guiones y guiones bajos.',
            'password.required' => 'Indicá la contraseña de la cuenta.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
            'password.symbols' => 'La contraseña debe incluir al menos un símbolo.',
        ];
    }
}
