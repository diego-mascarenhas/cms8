<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestMessageFromTemplateRequest extends FormRequest
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
            'template_id' => [
                'required',
                'integer',
                'min:1',
                ...($teamId !== null
                    ? [Rule::exists('templates', 'id')->where(static fn ($query) => $query->where('team_id', $teamId))]
                    : [Rule::prohibited()]),
            ],
            'draft_name' => ['nullable', 'string', 'max:255'],
            'fallback_text' => ['nullable', 'string', 'max:65535'],
            'test_recipients' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
