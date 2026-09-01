<?php

namespace App\Http\Requests\Api;

use App\Services\AiCompletionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', Rule::in(AiCompletionService::ALLOWED_MODULES)],
            'prompt' => ['required', 'string', 'min:1', 'max:100000'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:16000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'service' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'module.required' => __('Indicá el módulo de IA.'),
            'module.in' => __('El módulo de IA no es válido.'),
            'prompt.required' => __('El prompt es obligatorio.'),
        ];
    }
}
