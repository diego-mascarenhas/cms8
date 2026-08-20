<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfilePhotoRequest extends FormRequest
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
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => __('Elegí una foto de perfil.'),
            'photo.image' => __('La foto debe ser una imagen.'),
            'photo.mimes' => __('Usá JPG, PNG o WebP.'),
            'photo.max' => __('La foto no puede superar los 2 MB.'),
        ];
    }
}
