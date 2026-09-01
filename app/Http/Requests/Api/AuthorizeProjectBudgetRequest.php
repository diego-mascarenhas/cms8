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

        $user = $this->user();

        if (! $user?->hasRole('admin'))
        {
            return false;
        }

        return $user->can('update', $project);
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
