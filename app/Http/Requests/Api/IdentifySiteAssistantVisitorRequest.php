<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IdentifySiteAssistantVisitorRequest extends FormRequest
{
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'session_key' => ['nullable', 'string', 'max:191'],
            'attribution' => ['nullable', 'array'],
            'attribution.oppref' => ['nullable', 'string', 'max:191'],
            'attribution.utm_source' => ['nullable', 'string', 'max:80'],
            'attribution.utm_medium' => ['nullable', 'string', 'max:80'],
            'attribution.utm_campaign' => ['nullable', 'string', 'max:80'],
            'attribution.utm_content' => ['nullable', 'string', 'max:80'],
            'attribution.utm_term' => ['nullable', 'string', 'max:80'],
            'attribution.tag' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Email is required.'),
            'email.email' => __('Enter a valid email address.'),
        ];
    }
}
