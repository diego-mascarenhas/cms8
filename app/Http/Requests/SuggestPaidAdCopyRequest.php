<?php

namespace App\Http\Requests;

use App\Enums\PaidAdObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SuggestPaidAdCopyRequest extends FormRequest
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
            'goal' => ['required', 'string', 'min:10', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', new Enum(PaidAdObjective::class)],
            'locations' => ['nullable', 'string', 'max:1000'],
            'platforms' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'goal.required' => __('Contá qué querés lograr con esta campaña.'),
            'goal.min' => __('El contexto tiene que tener al menos 10 caracteres.'),
            'goal.max' => __('El contexto no puede superar los 2000 caracteres.'),
            'objective.Illuminate\Validation\Rules\Enum' => __('Elegí un objetivo válido.'),
        ];
    }
}
