<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateList60InboxPromptRequest extends FormRequest
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
            'prompt_instruction' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt_instruction.required' => __('team_settings.list60_prompt.instruction_required'),
        ];
    }
}
