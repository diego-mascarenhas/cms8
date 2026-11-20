<?php

namespace App\Http\Requests\Api;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = Order::query()->find($this->route('id'));

        if (! $order)
        {
            return false;
        }

        return $this->user()?->can('update', $order) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->blankMissingProductIds();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->user()?->current_team_id ?? 0);

        return [
            'payment_status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded', 'cancelled'])],
            'delivery_status' => ['sometimes', 'string', Rule::in(['processing', 'dispatched', 'delivered', 'out_for_delivery', 'cancelled'])],
            'notes' => ['sometimes', 'nullable', 'string'],
            'store_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('stores', 'id')->where('team_id', $teamId),
            ],
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('team_id', $teamId),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.category_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function blankMissingProductIds(): void
    {
        $items = $this->input('items');
        if (! is_array($items))
        {
            return;
        }

        $this->merge([
            'items' => array_map(function (mixed $item): mixed
            {
                if (! is_array($item))
                {
                    return $item;
                }

                if (! isset($item['product_id']) || (int) $item['product_id'] < 1)
                {
                    $item['product_id'] = null;
                }

                return $item;
            }, $items),
        ]);
    }
}
