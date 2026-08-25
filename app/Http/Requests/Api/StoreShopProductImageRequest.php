<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Subí una imagen del producto.'),
            'file.image' => __('El archivo tiene que ser una imagen.'),
            'file.mimes' => __('Usá JPG, PNG o WebP.'),
            'file.max' => __('La imagen no puede superar los 8 MB.'),
        ];
    }
}
