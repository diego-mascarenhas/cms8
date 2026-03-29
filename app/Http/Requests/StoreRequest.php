<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $teamId = auth()->user()?->currentTeam?->id;
        $storeId = $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stores', 'name')
                    ->where(fn ($query) => $query->where('team_id', $teamId)->whereNull('deleted_at'))
                    ->ignore($storeId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('stores', 'code')
                    ->where(fn ($query) => $query->where('team_id', $teamId)->whereNull('deleted_at'))
                    ->ignore($storeId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
            'checkout_payment_methods' => ['required', 'array', 'min:1'],
            'checkout_payment_methods.*' => ['string', Rule::in(Store::checkoutPaymentMethodKeys())],
            'checkout_fulfillment_types' => ['required', 'array', 'min:1'],
            'checkout_fulfillment_types.*' => ['string', Rule::in(Store::checkoutFulfillmentKeys())],
        ];
    }
}
