<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleDigestReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'schedule_action' => ['required', 'string', 'in:email,whatsapp'],
            'schedule_recipient' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'schedule_subject' => ['nullable', 'string', 'max:255'],
            'highlight_key' => ['nullable', 'string', 'max:64'],
            'digest_message_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
