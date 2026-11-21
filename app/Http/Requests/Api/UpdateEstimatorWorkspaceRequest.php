<?php

namespace App\Http\Requests\Api;

use App\Support\EstimatorWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstimatorWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in([EstimatorWorkspace::BUDGET, EstimatorWorkspace::ORDER])],
        ];
    }
}
