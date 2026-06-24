<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendContactOutreachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['whatsapp', 'email'])],
            'message' => ['required', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }
}
