<?php

namespace App\Http\Requests;

use App\Models\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentAccountRequest extends FormRequest
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
        /** @var PaymentAccount $paymentAccount */
        $paymentAccount = $this->route('paymentAccount');
        $teamId = (int) auth()->user()->currentTeam->id;

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('payment_accounts', 'code')
                    ->where(fn ($query) => $query->where('team_id', $teamId))
                    ->ignore($paymentAccount->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
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
        return (new StorePaymentAccountRequest)->attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StorePaymentAccountRequest)->messages();
    }
}
