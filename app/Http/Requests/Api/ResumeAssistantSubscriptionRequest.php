<?php

namespace App\Http\Requests\Api;

use App\Support\HumanoPricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResumeAssistantSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->currentTeam;

        return $team !== null && $user->ownsTeam($team);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'catalog' => ['nullable', 'string', Rule::in(HumanoPricingCatalog::all())],
        ];
    }
}
