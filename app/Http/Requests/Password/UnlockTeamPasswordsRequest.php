<?php

namespace App\Http\Requests\Password;

use Illuminate\Foundation\Http\FormRequest;

class UnlockTeamPasswordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->currentTeam !== null;
    }

    public function rules(): array
    {
        return [
            'master_key' => ['required', 'string'],
        ];
    }
}
