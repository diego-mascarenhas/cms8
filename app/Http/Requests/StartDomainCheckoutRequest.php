<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDomainCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->attributes->get('auth_via') === 'team_token')
        {
            return $this->user() !== null;
        }

        $user = $this->user();

        return $user !== null && $user->canAccessInfrastructure();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:253'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'size:2'],
            'tax_id' => ['required', 'string', 'max:32'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'success_url' => ['required', 'string', 'max:2048'],
            'cancel_url' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'Escribí un dominio.',
            'name.required' => 'Escribí tu nombre.',
            'email.required' => 'Escribí tu email.',
            'email.email' => 'El email no es válido.',
            'phone.required' => 'Escribí un teléfono.',
            'country.required' => 'Elegí un país.',
            'tax_id.required' => 'Escribí la identificación fiscal.',
        ];
    }
}
