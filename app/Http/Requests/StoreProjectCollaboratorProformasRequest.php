<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectCollaboratorProformasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collaborators' => ['required', 'array', 'min:1'],
            'collaborators.*.selected' => ['nullable', 'in:1'],
            'collaborators.*.user_id' => ['required', 'integer'],
            'collaborators.*.mode' => ['required', Rule::in(['percent', 'amount'])],
            'collaborators.*.value' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'collaborators.required' => __('Seleccioná al menos un colaborador.'),
            'collaborators.*.mode.in' => __('Elegí porcentaje o importe fijo.'),
            'collaborators.*.value.min' => __('El valor debe ser mayor que cero.'),
        ];
    }

    /**
     * @return list<array{user_id: int, mode: string, value: float}>
     */
    public function collaboratorLines(): array
    {
        $lines = [];

        foreach ($this->validated('collaborators') as $row)
        {
            if (! is_array($row) || ($row['selected'] ?? null) !== '1')
            {
                continue;
            }

            $lines[] = [
                'user_id' => (int) $row['user_id'],
                'mode' => (string) $row['mode'],
                'value' => (float) $row['value'],
            ];
        }

        return $lines;
    }
}
