<?php

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;

class CreateTeamPasswordShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->currentTeam)
        {
            return false;
        }

        return $user->can('view', $this->route('team_password'));
    }

    public function rules(): array
    {
        return [];
    }
}
