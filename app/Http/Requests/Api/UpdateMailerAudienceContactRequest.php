<?php

namespace App\Http\Requests\Api;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMailerAudienceContactRequest extends FormRequest
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
            $trimmed = Str::lower(trim($email));
            $this->merge(['email' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->user()?->currentTeam?->id ?? 0);
        $contactId = (int) $this->route('id');
        $email = $this->input('email');
        $email = is_string($email) ? $email : '';

        $emailRules = ['nullable', 'email:rfc', 'max:255'];
        if ($email !== '' && ! $this->emailUnchangedForContact($contactId, $teamId, $email))
        {
            $emailRules[] = Rule::unique('contacts', 'email')
                ->where(fn ($query) => $query->where('team_id', $teamId))
                ->ignore($contactId);
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => $emailRules,
            'status_id' => ['nullable', 'integer', 'exists:contact_statuses,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }

    private function emailUnchangedForContact(int $contactId, int $teamId, string $email): bool
    {
        if ($contactId < 1 || $email === '')
        {
            return false;
        }

        $current = Contact::withoutGlobalScopes()
            ->whereKey($contactId)
            ->where('team_id', $teamId)
            ->value('email');

        return is_string($current) && Str::lower(trim($current)) === $email;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('El nombre es obligatorio.'),
            'email.email' => __('Ingresá un email válido.'),
            'email.unique' => __('Ese email ya está en la audiencia.'),
        ];
    }
}
