<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('payment_accounts', 'code')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'name' => ['required', 'string', 'max:100'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'status' => ['required', 'integer', Rule::in([0, 1])],
            'payment_type_ids' => ['required', 'array', 'min:1'],
            'payment_type_ids.*' => ['integer', 'exists:payment_types,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'código',
            'name' => 'nombre',
            'currency_id' => 'moneda',
            'status' => 'estado',
            'payment_type_ids' => 'formas de pago aceptadas',
            'payment_type_ids.*' => 'forma de pago',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_type_ids.required' => 'Selecciona al menos una forma de pago aceptada por la cuenta.',
            'payment_type_ids.min' => 'Selecciona al menos una forma de pago aceptada por la cuenta.',
        ];
    }
}
