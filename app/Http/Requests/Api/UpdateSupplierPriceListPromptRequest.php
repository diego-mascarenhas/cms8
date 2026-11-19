<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierPriceListPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt_instruction' => ['required', 'string', 'min:20', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt_instruction.required' => __('The supplier list prompt is required.'),
            'prompt_instruction.min' => __('The supplier list prompt is too short.'),
            'prompt_instruction.max' => __('The supplier list prompt is too long.'),
        ];
    }
}
