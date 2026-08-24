<?php

namespace App\Http\Requests\Api;

use App\Support\HumanoPricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAssistantCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'plan' => ['nullable', 'string', Rule::in($this->allowedPlanIds())],
            'catalog' => ['nullable', 'string', Rule::in(HumanoPricingCatalog::all())],
            'success_url' => ['required', 'url', 'max:2048'],
            'cancel_url' => ['required', 'url', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interval.required' => __('Elegí si el plan es mensual o anual.'),
            'interval.in' => __('El intervalo debe ser mensual o anual.'),
            'plan.in' => __('El plan no es válido.'),
            'catalog.in' => __('El catálogo de planes no es válido.'),
            'success_url.required' => __('Falta la URL de retorno.'),
            'success_url.url' => __('La URL de retorno no es válida.'),
            'cancel_url.required' => __('Falta la URL de cancelación.'),
            'cancel_url.url' => __('La URL de cancelación no es válida.'),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedPlanIds(): array
    {
        return collect(config('humano_pricing.plans', []))
            ->pluck('id')
            ->map(fn (mixed $id): string => strtolower(trim((string) $id)))
            ->filter()
            ->merge(['basic', 'foundation', 'scale'])
            ->unique()
            ->values()
            ->all();
    }
}
