<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignSequenceRequest extends FormRequest
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

        $sequenceRules = [
            'manage_automations' => ['sometimes', 'boolean'],
            'sequence' => ['required', 'array', 'min:1'],
            'sequence.*.message_id' => [
                'required',
                'integer',
                Rule::exists('campaign_message', 'message_id')->where('campaign_id', $campaign->id),
            ],
            'sequence.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'sequence.*.delay_minutes_after_previous' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'sequence.*.condition_preset' => ['nullable', 'string', Rule::in(['none', 'opened', 'clicked'])],
        ];

        if ($this->boolean('manage_automations'))
        {
            $sequenceRules = array_merge($sequenceRules, [
                'sequence.*.automation' => ['nullable', 'array'],
                'sequence.*.automation.trigger' => ['nullable', 'string', Rule::in([
                    'after_previous_sent',
                    'if_opened_previous',
                    'if_not_opened_previous',
                    'delay_after_enrollment',
                ])],
                'sequence.*.automation.delay_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
                'sequence.*.automation.channel_type_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('message_type', 'id'),
                ],
                'sequence.*.automation.linked_message_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('messages', 'id')->where('team_id', $teamId),
                ],
                'sequence.*.automation.notes' => ['nullable', 'string', 'max:500'],
            ]);
        }

        return $sequenceRules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void
        {
            if (! $this->boolean('manage_automations'))
            {
                return;
            }
            foreach ($this->input('sequence', []) as $index => $seqRow)
            {
                if (! is_array($seqRow))
                {
                    continue;
                }
                $auto = $seqRow['automation'] ?? [];
                if (! is_array($auto))
                {
                    continue;
                }
                $hasTrigger = filled($auto['trigger'] ?? null);
                $hasChannel = filled($auto['channel_type_id'] ?? null);
                if ($hasTrigger xor $hasChannel)
                {
                    $validator->errors()->add(
                        "sequence.{$index}.automation.trigger",
                        __('Completa disparador y canal en la automatización de este paso, o déjalos vacíos.'),
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
            'sequence' => __('Secuencia'),
        ];
    }
}
