<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowBusinessProfileAssetRequest extends FormRequest
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
            'path' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'path.required' => __('Indicá la imagen a abrir.'),
        ];
    }
}
