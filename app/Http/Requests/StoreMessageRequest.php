<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'text' => ['required', 'string', 'min:3', 'max:255'],
            'min_hours_between_emails' => ['nullable', 'numeric', 'min:0', 'max:8760'],
            'send_allowed_weekdays' => ['required', 'array', 'min:1'],
            'send_allowed_weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'send_window_start' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_end'],
            'send_window_end' => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/', 'required_with:send_window_start'],
        ];
    }

    public function attributes(): array
    {
        return [
            'send_allowed_weekdays' => __('Allowed sending weekdays'),
            'send_window_start' => __('Sending window start'),
            'send_window_end' => __('Sending window end'),
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
