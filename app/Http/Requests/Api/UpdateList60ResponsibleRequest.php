<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateList60ResponsibleRequest extends FormRequest
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
            'responsible_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'responsible_id.required' => __('El asesor es obligatorio.'),
            'responsible_id.integer' => __('El asesor no es válido.'),
            'responsible_id.exists' => __('El asesor no es válido.'),
        ];
    }
}
