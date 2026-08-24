<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\ValidatesProjectFunnelIntake;
use Illuminate\Foundation\Http\FormRequest;

class SubmitProjectFunnelRequest extends FormRequest
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
            'brief' => ['required', 'string', 'min:20', 'max:16000'],
            'quote_token' => ['required', 'string'],
            'suggested_tasks' => ['nullable', 'array', 'max:20'],
            'suggested_tasks.*.title' => ['required_with:suggested_tasks', 'string', 'max:255'],
            'suggested_tasks.*.description' => ['nullable', 'string', 'max:2000'],
            'suggested_tasks.*.category_name' => ['nullable', 'string', 'max:255'],
            'suggested_tasks.*.estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'suggested_tasks.*.resource_level' => ['nullable', 'string', 'max:80'],
            'suggested_tasks.*.included' => ['nullable', 'boolean'],
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
            'quote_token.required' => __('Generate a quote before submitting.'),
            ...$this->funnelIntakeMessages(),
        ];
    }
}
