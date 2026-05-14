<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMessageTemplateHtmlForEditorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $mid = $this->input('message_id');
        if ($mid === '' || $mid === null)
        {
            $this->merge(['message_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'message_id' => [
                'nullable',
                'integer',
                'min:1',
                ...($teamId !== null
                    ? [Rule::exists('messages', 'id')->where(static fn ($query) => $query->where('team_id', $teamId))]
                    : [Rule::prohibited()]),
            ],
            'template_html' => ['required', 'string', 'min:1', 'max:819200'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
