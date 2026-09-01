<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFromBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('business_name') && $this->filled('client_name'))
        {
            $this->merge(['business_name' => $this->input('client_name')]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enterprise_id' => ['required_without:business_name', 'nullable', 'integer', 'exists:enterprises,id'],
            'business_name' => ['required_without:enterprise_id', 'nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required_without:enterprise_id', 'nullable', 'string', 'max:120'],
            'surname' => ['nullable', 'string', 'max:120'],
            'email' => ['required_without:enterprise_id', 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:120'],
            'category_ids' => ['nullable', 'array', 'max:20'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'brief' => ['required', 'string', 'min:10', 'max:16000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enterprise_id.required_without' => __('The client is required.'),
            'enterprise_id.exists' => __('The selected client is invalid.'),
            'business_name.required_without' => __('The client is required.'),
            'contact_name.required_without' => __('First name is required.'),
            'email.required_without' => __('Email is required.'),
            'email.email' => __('Enter a valid email address.'),
            'brief.required' => __('The budget text is required.'),
            'brief.min' => __('The budget text must be at least 10 characters.'),
        ];
    }
}
