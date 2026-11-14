<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHostingClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessInfrastructure() === true;
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
            'enterprise_id' => ['nullable', 'integer', $enterpriseRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enterprise_id.exists' => 'El cliente seleccionado no es válido para tu equipo.',
        ];
    }
}
