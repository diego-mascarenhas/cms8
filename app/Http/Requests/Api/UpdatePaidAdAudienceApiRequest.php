<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaidAdAudienceApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['custom', 'lookalike', 'retargeting', 'saved'])],
            'targeting_rules' => ['nullable', 'array'],
            'targeting_rules.locations' => ['nullable', 'string', 'max:1000'],
            'targeting_rules.interests' => ['nullable', 'string', 'max:1000'],
            'targeting_rules.age_min' => ['nullable', 'integer', 'min:13', 'max:99'],
            'targeting_rules.age_max' => ['nullable', 'integer', 'min:13', 'max:99'],
            'estimated_size' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
