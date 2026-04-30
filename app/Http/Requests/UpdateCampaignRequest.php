<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');
        $teamId = auth()->user()?->current_team_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'send_time_zone' => ['nullable', 'timezone:all'],
            'sequence' => ['nullable', 'array'],
            'sequence.*.message_id' => [
                'required',
                'integer',
                Rule::exists('campaign_message', 'message_id')->where('campaign_id', $campaign->id),
            ],
            'sequence.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'sequence.*.delay_minutes_after_previous' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'sequence.*.condition_preset' => ['nullable', 'string', Rule::in(['none', 'opened', 'clicked'])],
            'automations' => ['nullable', 'array'],
            'automations.*.trigger' => ['nullable', 'string', Rule::in([
                'after_previous_sent',
                'if_opened_previous',
                'if_not_opened_previous',
                'delay_after_enrollment',
            ])],
            'automations.*.delay_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'automations.*.channel_type_id' => [
                'nullable',
                'integer',
                Rule::exists('message_type', 'id'),
            ],
            'automations.*.message_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('team_id', $teamId),
            ],
            'automations.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void
        {
            $automations = $this->input('automations', []);
            foreach ($automations as $index => $row)
            {
                if (! is_array($row))
                {
                    continue;
                }
                $hasTrigger = filled($row['trigger'] ?? null);
                $hasChannel = filled($row['channel_type_id'] ?? null);
                if ($hasTrigger xor $hasChannel)
                {
                    $validator->errors()->add(
                        "automations.{$index}.trigger",
                        __('Completa disparador y canal para cada automatización, o deja la fila vacía.'),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Título interno'),
            'send_time_zone' => __('Zona horaria predeterminada'),
            'sequence' => __('Secuencia'),
            'automations' => __('Automatizaciones'),
        ];
    }
}
