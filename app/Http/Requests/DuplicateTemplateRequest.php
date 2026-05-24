<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'duplicate_template_name' => ['required', 'string', 'min:3', 'max:75'],
            'return_url' => ['nullable', 'string', 'max:2048'],
            'message_id' => [
                'nullable',
                'integer',
                'min:1',
                ...($teamId !== null
                    ? [Rule::exists('messages', 'id')->where(static fn ($query) => $query->where('team_id', $teamId))]
                    : [Rule::prohibited()]),
            ],
            'template_html' => ['nullable', 'string', 'max:819200'],
        ];
    }
}
