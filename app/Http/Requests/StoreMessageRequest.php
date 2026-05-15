<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
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

        if ($this->has('schedule_send_at') && $this->input('schedule_send_at') === '')
        {
            $this->merge(['schedule_send_at' => null]);
        }

        foreach (['show_unsubscribe', 'enable_open_tracking', 'enable_click_tracking'] as $flag)
        {
            if (! $this->has($flag))
            {
                $this->merge([$flag => '1']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'save_intent' => ['nullable', 'string', 'in:save,save_send,save_schedule'],
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'text' => ['required', 'string', 'min:3', 'max:255'],
            'contact_status_id' => ['nullable', 'integer', 'exists:contact_statuses,id'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
            'template_html' => ['nullable', 'string'],
            'min_hours_between_emails' => ['nullable', 'numeric', 'min:0', 'max:8760'],
            'send_allowed_weekdays' => ['required', 'array', 'min:1'],
            'send_allowed_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'send_window_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_end'],
            'send_window_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_start'],
            'schedule_send_at' => [
                'nullable',
                'string',
                'max:64',
                Rule::requiredIf(fn () => $this->input('save_intent') === 'save_schedule'),
                'date',
                'after:now',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'send_allowed_weekdays' => __('Allowed sending weekdays'),
            'send_window_start' => __('Sending window start'),
            'send_window_end' => __('Sending window end'),
            'schedule_send_at' => __('app.message_schedule_send_at_label'),
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
