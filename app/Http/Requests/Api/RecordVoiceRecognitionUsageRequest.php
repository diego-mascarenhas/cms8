<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RecordVoiceRecognitionUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null || $this->attributes->get('team') !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'source' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer'],
            'external_ref' => ['nullable', 'string', 'max:160'],
            'recognized_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'seconds.required' => __('Indica los segundos reconocidos.'),
            'seconds.integer' => __('Los segundos reconocidos tienen que ser un número.'),
            'seconds.min' => __('Los segundos reconocidos tienen que ser al menos 1.'),
            'seconds.max' => __('Los segundos reconocidos no pueden superar un día.'),
            'recognized_at.date' => __('La fecha de reconocimiento no es válida.'),
        ];
    }
}
