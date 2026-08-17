<?php

namespace App\Http\Requests;

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
        $planIds = collect(config('humano_pricing.plans', []))
            ->filter(function (mixed $plan): bool
            {
                if (! is_array($plan))
                {
                    return false;
                }

                $checkoutUrl = trim((string) ($plan['checkout_url'] ?? ''));

                return $checkoutUrl !== ''
                    && (bool) ($plan['checkout_available'] ?? false)
                    && (bool) ($plan['public'] ?? true);
            })
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return [
            'invite_name' => ['required', 'string', 'max:255'],
            'invite_email' => ['required', 'email', 'max:255'],
            'invite_plan' => ['required', 'string', Rule::in($planIds)],
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
