<?php

namespace App\Http\Requests\Api;

use App\Services\AiCompletionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAiUsageRequest extends FormRequest
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
            'service' => ['nullable', 'string', 'max:120'],
            'prompt_tokens' => ['nullable', 'integer', 'min:0'],
            'completion_tokens' => ['nullable', 'integer', 'min:0'],
            'total_tokens' => ['nullable', 'integer', 'min:0'],
            'input_size' => ['nullable', 'integer', 'min:0'],
            'output_size' => ['nullable', 'integer', 'min:0'],
            'toon' => ['nullable', 'array'],
            'toon.used_toon' => ['nullable', 'boolean'],
            'toon.json_size' => ['nullable', 'integer', 'min:0'],
            'toon.toon_size' => ['nullable', 'integer', 'min:0'],
            'toon.json_tokens' => ['nullable', 'integer', 'min:0'],
            'toon.toon_tokens' => ['nullable', 'integer', 'min:0'],
            'toon.tokens_saved' => ['nullable', 'integer', 'min:0'],
            'toon.savings_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
        ];
    }
}
