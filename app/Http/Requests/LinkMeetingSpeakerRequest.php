<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkMeetingSpeakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'speaker' => ['required', 'string', 'max:40'],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(function ($query) use ($teamId)
                {
                    $query->where('team_id', $teamId)->whereNull('deleted_at');
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'speaker.required' => 'Elegí la voz que querés vincular.',
            'contact_id.exists' => 'Esa persona no está en el equipo.',
        ];
    }
}
