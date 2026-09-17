<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectFunnelTokenPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'input_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'output_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'token_model' => ['nullable', 'array'],
            'token_model.id' => ['required_with:token_model', 'string', 'max:255'],
            'token_model.name' => ['nullable', 'string', 'max:255'],
            'token_model.prompt_per_million' => ['nullable', 'numeric', 'min:0'],
            'token_model.completion_per_million' => ['nullable', 'numeric', 'min:0'],
            'discriminate' => ['required', 'boolean'],
            'include' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'input_rate.required' => __('The input token price is required.'),
            'input_rate.numeric' => __('The input token price is not valid.'),
            'output_rate.required' => __('The output token price is required.'),
            'output_rate.numeric' => __('The output token price is not valid.'),
            'discriminate.required' => __('Choose whether to show tokens separately.'),
            'include.required' => __('Choose whether to add tokens to the labors.'),
        ];
    }
}
