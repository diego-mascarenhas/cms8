<?php

namespace App\Http\Requests;

use App\Support\HumanoPricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAffiliateInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null && $team->canUseAffiliateProgram();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalog = HumanoPricingCatalog::normalize((string) $this->input('catalog', ''));

        $planIds = collect(config('humano_pricing.plans', []))
            ->filter(function (mixed $plan) use ($catalog): bool
            {
                if (! is_array($plan))
                {
                    return false;
                }

                $checkoutUrl = trim((string) ($plan['checkout_url'] ?? ''));
                $planCatalog = strtolower(trim((string) ($plan['catalog'] ?? 'assistant')));

                if ($checkoutUrl === '' || ! (bool) ($plan['checkout_available'] ?? false))
                {
                    return false;
                }

                return $catalog === null || $planCatalog === $catalog;
            })
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return [
            'invite_name' => ['required', 'string', 'max:255'],
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_plan' => ['required', 'string', Rule::in($planIds)],
            'catalog' => ['nullable', 'string', Rule::in(HumanoPricingCatalog::all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'invite_name' => 'nombre',
            'invite_email' => 'email',
            'invite_plan' => 'plan',
        ];
    }
}
