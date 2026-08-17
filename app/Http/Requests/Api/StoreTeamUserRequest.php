<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('El nombre es obligatorio.'),
            'email.required' => __('El email es obligatorio.'),
            'email.email' => __('El email no es válido.'),
            'email.unique' => __('Ese email ya está en uso.'),
            'password.required' => __('La contraseña es obligatoria.'),
            'password.min' => __('La contraseña debe tener al menos 8 caracteres.'),
            'password.confirmed' => __('Las contraseñas no coinciden.'),
        ];
    }
}
