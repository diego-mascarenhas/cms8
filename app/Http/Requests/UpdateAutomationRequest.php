<?php

namespace App\Http\Requests;

use App\Models\Automation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Automation|null $automation */
        $automation = $this->route('automation');

        return $automation instanceof Automation
            && ($this->user()?->can('update', $automation) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;
        /** @var Automation $automation */
        $automation = $this->route('automation');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('automations', 'slug')
                    ->where(fn ($q) => $q->where('team_id', $teamId))
                    ->ignore($automation->id),
            ],
            'entry_prompt_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'channels' => ['nullable', 'array'],
            'channels.humano' => ['sometimes', 'boolean'],
            'channels.whatsapp' => ['sometimes', 'boolean'],
            'channels.chat' => ['sometimes', 'boolean'],
            'channels.email' => ['sometimes', 'boolean'],
            'channels.api' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.welcome_message' => ['nullable', 'string', 'max:2000'],
            'settings.entry_aliases' => ['nullable', 'string', 'max:1000'],
            'regenerate_token' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('El nombre es obligatorio.'),
            'slug.regex' => __('El slug solo puede contener minúsculas, números y guiones.'),
            'slug.unique' => __('Ya existe una automatización con este slug en el equipo.'),
        ];
    }
}
