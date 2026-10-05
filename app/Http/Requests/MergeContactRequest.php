<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeContactRequest extends FormRequest
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
            'contact_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.required' => 'Elegí el contacto que querés fusionar.',
        ];
    }
}
