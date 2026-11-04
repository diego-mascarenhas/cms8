<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DemoProjectFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brief' => ['required', 'string', 'min:10', 'max:16000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brief.required' => __('Describe what you need.'),
            'brief.min' => __('Please add a bit more detail so we can estimate the work.'),
        ];
    }
}
