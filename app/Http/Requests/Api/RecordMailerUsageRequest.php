<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RecordMailerUsageRequest extends FormRequest
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
            'count' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'source' => ['nullable', 'string', 'max:64'],
            'sent_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'count.integer' => __('La cantidad de emails debe ser un número.'),
            'count.min' => __('La cantidad de emails debe ser al menos 1.'),
            'sent_at.date' => __('La fecha de envío no es válida.'),
        ];
    }
}
