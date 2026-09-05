<?php

namespace App\Http\Requests\Api;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicShopCheckoutRequest extends FormRequest
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
            'guest_id' => ['required', 'string', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.code' => ['required', 'string', 'max:120'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.detail' => ['nullable', 'string', 'max:500'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'store_id' => ['nullable', 'integer', 'min:1'],
            'fulfillment_type' => ['nullable', 'string', Rule::in(Store::checkoutFulfillmentKeys())],
            'payment_method' => ['nullable', 'string', Rule::in(Store::checkoutPaymentMethodKeys())],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'delivery_address' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => $this->input('fulfillment_type') === Store::CHECKOUT_FULFILLMENT_DELIVERY),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => __('Indicá tu nombre.'),
            'customer_phone.required' => __('Indicá tu teléfono o WhatsApp.'),
            'delivery_address.required' => __('Indicá la dirección de entrega.'),
        ];
    }
}
