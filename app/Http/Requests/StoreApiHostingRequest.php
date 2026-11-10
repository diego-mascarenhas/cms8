<?php

namespace App\Http\Requests;

use App\Rules\DomainWithExtension;
use App\Support\CpanelUsername;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApiHostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canAccessInfrastructure();
    }

    protected function prepareForValidation(): void
    {
        $mailboxes = collect($this->input('mailboxes', []))
            ->map(fn (mixed $local) => strtolower(trim((string) $local)))
            ->filter(fn (string $local) => $local !== '')
            ->values()
            ->all();

        $this->merge([
            'domain' => strtolower(trim((string) $this->input('domain', ''))),
            'username' => strtolower(trim((string) $this->input('username', ''))),
            'mailboxes' => $mailboxes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        $enterpriseRule = Rule::exists('enterprises', 'id');

        if ($teamId !== null)
        {
            $enterpriseRule = $enterpriseRule->where('team_id', $teamId);
        }

        return [
            'enterprise_id' => ['required', 'integer', $enterpriseRule],
            'domain' => ['required', 'string', 'max:253', new DomainWithExtension, 'unique:domains,domain'],
            'username' => ['required', 'string', 'max:16', 'regex:/^[a-z][a-z0-9_]{0,15}$/'],
            'mailboxes' => ['present', 'array', 'max:30'],
            'mailboxes.*' => ['string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]{0,63}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $username = (string) $this->input('username', '');

            if ($username !== '' && ! CpanelUsername::isValid($username))
            {
                $validator->errors()->add(
                    'username',
                    'El usuario cPanel debe empezar con letra, usar solo letras minúsculas, números o guión bajo, y tener máximo 16 caracteres.',
                );
            }

            $mailboxes = $this->input('mailboxes', []);

            if (is_array($mailboxes) && count($mailboxes) !== count(array_unique($mailboxes)))
            {
                $validator->errors()->add('mailboxes', 'Hay cuentas de correo repetidas.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enterprise_id.required' => 'Elige la empresa del hosting.',
            'enterprise_id.exists' => 'La empresa seleccionada no es válida para tu equipo.',
            'domain.required' => 'Indica el nombre de dominio.',
            'domain.unique' => 'Este dominio ya está registrado en hosting.',
            'username.required' => 'Indica el nombre de usuario cPanel.',
            'username.regex' => 'El usuario cPanel debe empezar con letra y contener solo letras minúsculas, números o guión bajo (máx. 16).',
        ];
    }
}
