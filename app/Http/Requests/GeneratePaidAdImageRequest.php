<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePaidAdImageRequest extends FormRequest
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
            'scene' => ['required', 'string', 'min:10', 'max:400'],
            'hook' => ['nullable', 'string', 'max:120'],
            'framing' => ['nullable', 'string', 'max:240'],
            'query' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scene.required' => __('Primero sugerí una imagen para poder generarla.'),
            'scene.min' => __('La escena es demasiado corta para generar la foto.'),
        ];
    }
}
