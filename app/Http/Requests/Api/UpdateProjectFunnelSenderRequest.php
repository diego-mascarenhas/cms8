<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectFunnelSenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mail_from_name.required' => __('The sender name is required.'),
            'mail_from_address.required' => __('The sender email is required.'),
            'mail_from_address.email' => __('The sender email is not valid.'),
        ];
    }
}
