<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'enterprise_id' => ['required', 'integer', 'exists:enterprises,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'Escribí un dominio.',
            'enterprise_id.required' => 'Indicá la empresa a la que se cobra el dominio.',
            'enterprise_id.exists' => 'La empresa no existe.',
        ];
    }
}
