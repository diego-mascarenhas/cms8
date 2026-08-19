<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamSiteAssistantPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && ($this->user()?->can('update', $team) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'section_label.required' => __('team_settings.site_assistant.label_required'),
            'prompt_instruction.required' => __('team_settings.site_assistant.instruction_required'),
        ];
    }
}
