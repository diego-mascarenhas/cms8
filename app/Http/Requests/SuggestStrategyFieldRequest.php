<?php

namespace App\Http\Requests;

use App\Services\WeeklyWorkPlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuggestStrategyFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(app(WeeklyWorkPlanService::class)->allowedStrategyFieldKeys())],
            'draft' => ['nullable', 'string', 'max:5000'],
            'siblings' => ['nullable', 'array'],
            'siblings.*' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
