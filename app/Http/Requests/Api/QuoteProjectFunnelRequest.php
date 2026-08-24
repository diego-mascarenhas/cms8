<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\ValidatesProjectFunnelIntake;
use Illuminate\Foundation\Http\FormRequest;

class QuoteProjectFunnelRequest extends FormRequest
{
    use ValidatesProjectFunnelIntake;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'surname' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'brief' => ['required', 'string', 'min:10', 'max:16000'],
            ...$this->funnelIntakeRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('First name is required.'),
            'email.required' => __('Email is required.'),
            'email.email' => __('Enter a valid email address.'),
            'brief.required' => __('Describe what you need.'),
            'brief.min' => __('Please add a bit more detail so we can estimate the work.'),
            ...$this->funnelIntakeMessages(),
        ];
    }
}
