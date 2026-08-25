<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class DestroyAllTeamProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deleteAny', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => __('Ingresá tu contraseña para autorizar el borrado.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $password = (string) $this->input('password', '');
            if ($password === '')
            {
                return;
            }

            $user = $this->user();
            if ($user === null || ! Hash::check($password, (string) $user->password))
            {
                $validator->errors()->add('password', __('La contraseña no es correcta.'));
            }
        });
    }
}
