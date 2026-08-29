<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMailerNewsRequest extends FormRequest
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
            'goal_type' => ['required', 'string', Rule::in([
                'newsletter',
                'launch',
                'promo',
                'reactivate',
                'event',
                'onboarding',
                'other',
            ])],
            'goal' => ['required', 'string', 'min:10', 'max:2000'],
            'cta' => ['required', 'string', 'min:3', 'max:255'],
            'audience' => ['required', 'string', 'min:10', 'max:2000'],
            'offer' => ['required', 'string', 'min:10', 'max:2000'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'urgency' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2000'],
            'tone' => ['required', 'string', Rule::in([
                'close',
                'professional',
                'urgent',
                'educational',
            ])],
            'avoid' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'goal_type.required' => __('Elegí el tipo de campaña.'),
            'goal.required' => __('Contá qué tienen que lograr con este News.'),
            'goal.min' => __('El objetivo tiene que tener al menos 10 caracteres.'),
            'cta.required' => __('Decí qué tiene que hacer el lector.'),
            'audience.required' => __('Contá a quién le hablamos.'),
            'audience.min' => __('La audiencia tiene que tener al menos 10 caracteres.'),
            'offer.required' => __('Contá la novedad o la oferta.'),
            'offer.min' => __('La propuesta tiene que tener al menos 10 caracteres.'),
            'tone.required' => __('Elegí el tono del correo.'),
        ];
    }
}
