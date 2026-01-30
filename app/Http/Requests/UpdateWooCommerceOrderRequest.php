<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWooCommerceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Order::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|string|in:pending,processing,on-hold,completed,cancelled,refunded,failed',
            'customer_note' => 'nullable|string',
            'billing.first_name' => 'nullable|string|max:255',
            'billing.last_name' => 'nullable|string|max:255',
            'billing.company' => 'nullable|string|max:255',
            'billing.address_1' => 'nullable|string|max:255',
            'billing.address_2' => 'nullable|string|max:255',
            'billing.city' => 'nullable|string|max:255',
            'billing.state' => 'nullable|string|max:255',
            'billing.postcode' => 'nullable|string|max:50',
            'billing.country' => 'nullable|string|max:2',
            'billing.email' => 'nullable|email',
            'billing.phone' => 'nullable|string|max:50',
        ];
    }
}
