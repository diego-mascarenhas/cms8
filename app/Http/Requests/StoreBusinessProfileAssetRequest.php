<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessProfileAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'in:logo,image'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => __('Indicá si es el logo o una foto de marca.'),
            'role.in' => __('El archivo tiene que ser logo o foto de marca.'),
            'file.required' => __('Subí una imagen.'),
            'file.image' => __('El archivo tiene que ser una imagen.'),
            'file.mimes' => __('Usá JPG, PNG o WebP.'),
            'file.max' => __('La imagen no puede superar los 8 MB.'),
        ];
    }
}
