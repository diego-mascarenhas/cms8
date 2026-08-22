<?php

namespace App\Http\Requests;

use App\Enums\AdCreativeFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePaidAdCreativeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', new Enum(AdCreativeFormat::class)],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.required' => __('Elegí el formato de la pieza.'),
            'file.required' => __('Subí una imagen para este formato.'),
            'file.image' => __('El archivo tiene que ser una imagen.'),
            'file.mimes' => __('Usá JPG, PNG o WebP.'),
            'file.max' => __('La imagen no puede superar los 8 MB.'),
        ];
    }
}
