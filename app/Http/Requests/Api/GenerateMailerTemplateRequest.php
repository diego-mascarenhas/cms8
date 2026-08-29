<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMailerTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
            'name' => ['nullable', 'string', 'max:75'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => __('Contá qué tipo de plantilla querés crear.'),
            'prompt.min' => __('El pedido tiene que tener al menos 10 caracteres.'),
            'prompt.max' => __('El pedido no puede superar los 2000 caracteres.'),
        ];
    }
}
