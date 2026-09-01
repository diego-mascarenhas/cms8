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
            'input_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'output_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'discriminate' => ['required', 'boolean'],
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
        ];
    }
}
