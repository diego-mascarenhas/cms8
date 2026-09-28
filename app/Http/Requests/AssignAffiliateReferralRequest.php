<?php

namespace App\Http\Requests;

use App\Support\AffiliateDirectoryAccess;
use Illuminate\Foundation\Http\FormRequest;

class AssignAffiliateReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AffiliateDirectoryAccess::allows($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'subscription_code' => ['required', 'string', 'max:64', 'regex:/^(cus_|sub_)/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subscription_code.regex' => 'El código debe empezar por cus_ o sub_.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_id' => 'afiliado',
            'subscription_code' => 'código',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subscription_code' => trim((string) $this->input('subscription_code', '')),
        ]);
    }
}
