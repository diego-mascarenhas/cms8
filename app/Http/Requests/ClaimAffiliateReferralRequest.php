<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimAffiliateReferralRequest extends FormRequest
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
        return [
            'subscription_code' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subscription_code' => 'código de suscripción',
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('subscription_code', ''));

        $this->merge([
            'subscription_code' => $code,
        ]);
    }
}
