<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteDomainCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->attributes->get('auth_via') === 'team_token')
        {
            return $this->user() !== null;
        }

        $user = $this->user();

        return $user !== null && $user->canAccessInfrastructure();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['required', 'string', 'starts_with:cs_', 'max:255'],
        ];
    }
}
