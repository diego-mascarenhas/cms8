<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMailerSenderApiRequest extends FormRequest
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
            'mail_from_name.required' => __('app.email_sender_modal_from_name').' es obligatorio.',
            'mail_from_address.required' => __('app.email_sender_modal_from_email').' es obligatorio.',
            'mail_from_address.email' => __('app.email_sender_modal_from_email').' no es un email válido.',
        ];
    }
}
