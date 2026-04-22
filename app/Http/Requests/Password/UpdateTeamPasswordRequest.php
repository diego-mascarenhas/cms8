<?php

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->currentTeam)
        {
            return false;
        }

        return $this->user()->can('update', $this->route('team_password'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
            'enterprise_id' => [
                'nullable',
                'integer',
                Rule::exists('enterprises', 'id')->where(function ($query)
                {
                    $query->where('team_id', $this->user()->currentTeam->id);
                }),
            ],
        ];
    }
}
