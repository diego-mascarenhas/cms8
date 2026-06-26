<?php

namespace App\Http\Requests;

use App\Rules\DomainWithExtension;
use App\Support\CpanelUsername;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHostingRequest extends FormRequest
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

        if ($this->has('enterprise_id'))
        {
            $this->merge([
                'enterprise_id' => $this->input('enterprise_id') ?: null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        $serverRule = Rule::exists('servers', 'id');

        if ($teamId !== null)
        {
            $serverRule = $serverRule->where('team_id', $teamId);
        }

        $enterpriseRule = Rule::exists('enterprises', 'id');

        if ($teamId !== null)
        {
            $enterpriseRule = $enterpriseRule->where('team_id', $teamId);
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
            'domain' => ['required', 'string', 'max:253', new DomainWithExtension, 'unique:domains,domain'],
            'server_id' => ['required', 'integer', 'filled', $serverRule],
            'enterprise_id' => ['nullable', 'integer', $enterpriseRule, 'required_without:service_id'],
            'service_id' => ['nullable', 'integer', $serviceRule, 'required_without:enterprise_id'],
            'username' => ['required', 'string', 'min:1', 'max:16', 'regex:/^[a-z][a-z0-9_]{0,15}$/'],
            'plan' => ['required', 'string', 'max:255'],
            'site_type' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
            'enterprise_id.required_without' => 'Selecciona la empresa o un servicio existente.',
            'enterprise_id.exists' => 'La empresa seleccionada no es válida para tu equipo.',
            'service_id.required_without' => 'Selecciona un servicio existente o la empresa para crear uno nuevo.',
            'service_id.exists' => 'El servicio seleccionado no es válido para tu equipo.',
            'username.required' => 'Indica el nombre de usuario cPanel.',
            'username.regex' => 'El usuario cPanel debe empezar con letra y contener solo letras minúsculas, números o guión bajo (máx. 16).',
            'plan.required' => 'Selecciona un plan de hosting.',
        ];
    }
}
