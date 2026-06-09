<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWebDavAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'dav_username' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
