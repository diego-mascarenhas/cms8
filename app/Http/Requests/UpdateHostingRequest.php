<?php

namespace App\Http\Requests;

use App\Models\Domain;
use App\Rules\DomainWithExtension;
use App\Support\CpanelUsername;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('domain'))
        {
            $this->merge([
                'domain' => strtolower(trim((string) $this->input('domain'))),
            ]);
        }

        if ($this->has('username'))
        {
            $this->merge([
                'username' => strtolower(trim((string) $this->input('username'))),
            ]);
        }

        if ($this->has('service_id'))
        {
            $this->merge([
                'service_id' => $this->input('service_id') ?: null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Domain|null $hosting */
        $hosting = $this->route('hosting');
        $teamId = $this->user()?->currentTeam?->id;

        $serverRule = Rule::exists('servers', 'id');

        if ($teamId !== null)
        {
            $serverRule = $serverRule->where('team_id', $teamId);
        }

        $serviceRule = Rule::exists('services', 'id');

        if ($teamId !== null)
        {
            $serviceRule = $serviceRule->whereIn(
                'enterprise_id',
                fn ($query) => $query->select('id')->from('enterprises')->where('team_id', $teamId),
            );
        }

        return [
            'domain' => [
                'required',
                'string',
                'max:253',
                new DomainWithExtension,
                Rule::unique('domains', 'domain')->ignore($hosting?->id),
            ],
            'server_id' => ['required', 'integer', 'filled', $serverRule],
            'service_id' => ['nullable', 'integer', $serviceRule],
            'username' => ['required', 'string', 'min:1', 'max:16', 'regex:/^[a-z][a-z0-9_]{0,15}$/'],
            'plan' => ['required', 'string', 'max:255'],
            'site_type' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'needs_update' => ['nullable', 'boolean'],
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
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.required' => 'Indica el nombre de dominio.',
            'domain.unique' => 'Este dominio ya está registrado en hosting.',
            'server_id.required' => 'Selecciona un servidor.',
            'server_id.exists' => 'El servidor seleccionado no es válido para tu equipo.',
            'service_id.exists' => 'El servicio seleccionado no es válido para tu equipo.',
            'username.required' => 'Indica el nombre de usuario cPanel.',
            'username.regex' => 'El usuario cPanel debe empezar con letra y contener solo letras minúsculas, números o guión bajo (máx. 16).',
            'plan.required' => 'Selecciona un plan de hosting.',
        ];
    }
}
