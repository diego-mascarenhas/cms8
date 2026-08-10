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
            'discount' => 'nullable|numeric|min:0|max:100',
            'data' => 'nullable|array',
            'data.budget_given' => 'nullable|string',
            'data.ai_interpretation' => 'nullable|string',
            'data.dimension' => 'nullable|string',
            'data.estimated_times' => 'nullable|string',
            'data.resources' => 'nullable|string',
            'data.ai_usage_percent' => 'nullable|numeric|min:0|max:100',
            'data.token_consumption' => 'nullable|array',
            'data.token_consumption.notes' => 'nullable|string',
            'data.token_consumption.input_tokens' => 'nullable|integer|min:0',
            'data.token_consumption.output_tokens' => 'nullable|integer|min:0',
            'data.token_consumption.total_tokens' => 'nullable|integer|min:0',
            'data.token_consumption.cost_euros' => 'nullable|numeric|min:0',
            'data.token_consumption.savings_percent' => 'nullable|numeric|min:0|max:100',
            'data.token_consumption.billable_euros' => 'nullable|numeric|min:0',
            'data.token_consumption.currency' => 'nullable|string|max:8',
            'data.budget_preview_html' => 'nullable|string',
            'data.suggested_tasks' => 'nullable',
        ];
    }
}
