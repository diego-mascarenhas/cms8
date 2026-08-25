<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShoppingCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', new Order([
            'team_id' => $this->user()->currentTeam?->id,
        ])) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:0|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('Agregá al menos un producto.'),
            'items.*.quantity.required' => __('La cantidad es obligatoria.'),
            'items.*.quantity.min' => __('La cantidad no puede ser negativa.'),
            'items.*.quantity.max' => __('La cantidad máxima es 500.'),
        ];
    }
}
