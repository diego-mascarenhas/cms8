<?php

namespace App\Http\Requests\Api;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class AuthorizeProjectBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::query()->find($this->route('id'));

        if (! $project)
        {
            return false;
        }

        return $this->user()?->can('update', $project) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
