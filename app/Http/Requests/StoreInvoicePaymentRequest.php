<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoicePaymentRequest extends FormRequest
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
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('payment_accounts', 'id')->where(fn ($query) => $query
                    ->where('team_id', $teamId)
                    ->where('status', 1)),
            ],
            'type_id' => ['required', 'integer', 'exists:payment_types,id'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
