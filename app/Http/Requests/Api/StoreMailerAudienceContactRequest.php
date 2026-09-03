<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMailerAudienceContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email))
        {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->user()?->currentTeam?->id ?? 0);

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('contacts', 'email')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'status_id' => ['nullable', 'integer', 'exists:contact_statuses,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('El nombre es obligatorio.'),
            'email.required' => __('El email es obligatorio.'),
            'email.email' => __('Ingresá un email válido.'),
            'email.unique' => __('Ese email ya está en la audiencia.'),
        ];
    }
}
