<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateApiRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:75'],
            'status_id' => ['nullable', 'boolean'],
            'html' => ['nullable', 'string'],
            'css' => ['nullable', 'string'],
            'editor_json' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The template name is required.'),
            'name.min' => __('The template name must be at least :min characters.'),
            'name.max' => __('The template name may not be greater than :max characters.'),
        ];
    }
}
