<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class RegenerateProjectBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::query()->find($this->route('id'));

        return $project !== null && $this->user()?->can('update', $project);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('Describe what should change in the new estimate.'),
            'note.min' => __('Please write at least :min characters.', ['min' => 10]),
        ];
    }
}
