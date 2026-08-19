<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteAssistantPromptContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null && ($this->user()?->can('update', $team) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt_key' => ['required', 'string', 'max:255'],
            'section_label' => ['required', 'string', 'max:255'],
            'prompt_instruction' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt_key.required' => __('team_settings.site_assistant.invalid_prompt'),
            'section_label.required' => __('team_settings.site_assistant.label_required'),
            'prompt_instruction.required' => __('team_settings.site_assistant.instruction_required'),
        ];
    }
}
