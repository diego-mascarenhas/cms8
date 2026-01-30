<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWooCommerceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'nullable|string|max:20',
            'regular_price' => 'nullable|string|max:20',
            'sale_price' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'status' => 'nullable|string|in:draft,publish,pending',
            'stock_status' => 'nullable|string|in:instock,outofstock',
            'manage_stock' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
        ];
    }
}
