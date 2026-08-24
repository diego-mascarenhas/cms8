<?php

namespace App\Http\Requests\Api;

use App\Support\HumanoPricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelAssistantSubscriptionRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    public const REASONS = [
        'too_expensive',
        'missing_features',
        'unused',
        'too_complex',
        'switched_service',
        'low_quality',
        'other',
    ];

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
            'reason' => ['required', 'string', Rule::in(self::REASONS)],
            'catalog' => ['nullable', 'string', Rule::in(HumanoPricingCatalog::all())],
            'comment' => [
                Rule::requiredIf($this->input('reason') === 'other'),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('Elegí un motivo para cancelar.'),
            'reason.in' => __('El motivo de cancelación no es válido.'),
            'comment.required' => __('Contanos el motivo de la cancelación.'),
            'comment.max' => __('El comentario no puede superar los 500 caracteres.'),
        ];
    }
}
