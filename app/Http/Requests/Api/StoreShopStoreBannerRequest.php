<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopStoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Subí el banner de la tienda.'),
            'file.image' => __('El archivo tiene que ser una imagen.'),
            'file.mimes' => __('Usá JPG, PNG o WebP.'),
            'file.max' => __('El banner no puede superar los 5 MB.'),
        ];
    }
}
