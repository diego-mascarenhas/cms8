<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'real_name' => 'required|string|max:255',
            'status_id' => 'required|exists:project_statuses,id',
            'category_id' => 'nullable|exists:categories,id',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'enterprise_id' => 'required|exists:enterprises,id',
            'responsible_id' => 'required|exists:users,id',
            'date_material' => 'nullable|date',
            'description' => 'nullable|string',
        ];
    }
}
