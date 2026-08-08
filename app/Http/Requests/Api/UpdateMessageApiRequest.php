<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('contact_status_id') && ($this->input('contact_status_id') === '' || $this->input('contact_status_id') === null))
        {
            $this->merge(['contact_status_id' => null]);
        }

        if ($this->has('template_id') && ($this->input('template_id') === '' || $this->input('template_id') === null))
        {
            $this->merge(['template_id' => null]);
        }

        if ($this->has('message_category_ids') && ! is_array($this->input('message_category_ids')))
        {
            $this->merge(['message_category_ids' => []]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:50'],
            'text' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'type_id' => ['nullable', 'integer', 'exists:message_type,id'],
            'contact_status_id' => ['nullable', 'integer', 'exists:contact_statuses,id'],
            'message_category_ids' => ['nullable', 'array'],
            'message_category_ids.*' => ['integer', 'exists:categories,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'mail_html' => ['nullable', 'string'],
            'template_html' => ['nullable', 'string'],
            'status_id' => ['nullable', 'boolean'],
            'show_unsubscribe' => ['nullable', 'boolean'],
            'enable_open_tracking' => ['nullable', 'boolean'],
            'enable_click_tracking' => ['nullable', 'boolean'],
            'min_hours_between_emails' => ['nullable', 'numeric', 'min:0', 'max:8760'],
            'send_allowed_weekdays' => ['nullable', 'array', 'min:1'],
            'send_allowed_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'send_window_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_end'],
            'send_window_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_start'],
            'scheduled_send_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void
        {
            $start = $this->input('send_window_start');
            $end = $this->input('send_window_end');
            if (filled($start) && filled($end))
            {
                $startM = $this->minutesFromHi($start);
                $endM = $this->minutesFromHi($end);
                if ($startM === null || $endM === null)
                {
                    return;
                }
                if ($startM >= $endM)
                {
                    $v->errors()->add('send_window_end', __('The end time must be after the start time (same calendar day).'));
                }
            }
        });
    }

    private function minutesFromHi(?string $hi): ?int
    {
        if ($hi === null || $hi === '')
        {
            return null;
        }

        [$h, $m] = array_pad(explode(':', $hi), 2, 0);

        return ((int) $h) * 60 + (int) $m;
    }
}
