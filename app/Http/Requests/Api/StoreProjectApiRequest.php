<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectApiRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'real_name' => ['nullable', 'string', 'max:255'],
            'status_id' => ['required', 'exists:project_statuses,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'enterprise_id' => ['required', 'exists:enterprises,id'],
            'responsible_id' => ['required', 'exists:users,id'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
            'date_material' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('The project name is required.'),
            'status_id.required' => __('The project status is required.'),
            'status_id.exists' => __('The selected status is invalid.'),
            'enterprise_id.required' => __('The client is required.'),
            'enterprise_id.exists' => __('The selected client is invalid.'),
            'responsible_id.required' => __('The responsible user is required.'),
            'responsible_id.exists' => __('The selected responsible user is invalid.'),
            'date_end.after_or_equal' => __('The end date must be on or after the start date.'),
        ];
    }
}
