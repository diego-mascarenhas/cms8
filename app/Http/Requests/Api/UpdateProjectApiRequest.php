<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectApiRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'real_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', 'required', 'exists:project_statuses,id'],
            'category_id' => ['sometimes', 'nullable', 'exists:categories,id'],
            'enterprise_id' => ['sometimes', 'required', 'exists:enterprises,id'],
            'responsible_id' => ['sometimes', 'required', 'exists:users,id'],
            'date_start' => ['sometimes', 'nullable', 'date'],
            'date_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_start'],
            'date_material' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The project name is required.'),
            'status_id.exists' => __('The selected status is invalid.'),
            'enterprise_id.exists' => __('The selected client is invalid.'),
            'responsible_id.exists' => __('The selected responsible user is invalid.'),
            'date_end.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }
}
