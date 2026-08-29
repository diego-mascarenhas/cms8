<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('contact_status_id') === '' || $this->input('contact_status_id') === null)
        {
            $this->merge(['contact_status_id' => null]);
        }

        if ($this->input('template_id') === '' || $this->input('template_id') === null)
        {
            $this->merge(['template_id' => null]);
        }

        if (! $this->has('send_allowed_weekdays') || ! is_array($this->input('send_allowed_weekdays')) || count($this->input('send_allowed_weekdays')) === 0)
        {
            $this->merge(['send_allowed_weekdays' => range(1, 7)]);
        }

        if (! $this->filled('min_hours_between_emails'))
        {
            $this->merge(['min_hours_between_emails' => 48]);
        }

        if (! $this->has('type_id'))
        {
            $this->merge(['type_id' => 1]);
        }

        if (! $this->has('message_category_ids') || ! is_array($this->input('message_category_ids')))
        {
            $this->merge(['message_category_ids' => []]);
        }

        if ($this->filled('schedule_send_at') && ! $this->filled('scheduled_send_at'))
        {
            $this->merge(['scheduled_send_at' => $this->input('schedule_send_at')]);
        }

        foreach (['show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking', 'status_id'] as $flag)
        {
            if (! $this->has($flag))
            {
                $this->merge([$flag => $flag === 'status_id' ? false : true]);
            }
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'text' => ['required', 'string', 'min:3', 'max:255'],
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
            'send_allowed_weekdays' => ['required', 'array', 'min:1'],
            'send_allowed_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'send_window_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_end'],
            'send_window_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_start'],
            'scheduled_send_at' => ['nullable', 'date', 'after:now', 'required_if:save_intent,save_schedule'],
            'schedule_send_at' => ['nullable', 'date', 'after:now'],
            'save_intent' => ['nullable', 'in:save,save_send,save_schedule'],
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
