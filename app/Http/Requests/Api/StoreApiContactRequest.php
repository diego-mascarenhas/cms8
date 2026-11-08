<?php

namespace App\Http\Requests\Api;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:40'],
            'category_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.min' => 'El nombre es demasiado corto.',
            'email.required_without' => 'Indicá un email o un teléfono.',
            'email.email' => 'Ingresá un email válido.',
            'phone.required_without' => 'Indicá un teléfono o un email.',
        ];
    }
}
