<?php

namespace App\Http\Requests;

use App\Services\Business\BusinessProfileService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessProfileRequest extends FormRequest
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
        $rules = [];

        foreach (BusinessProfileService::EDITABLE_KEYS as $key)
        {
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }

        $rules['business_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['business_industry'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['business_tagline'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['business_website'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['business_email'] = ['sometimes', 'nullable', 'email', 'max:255'];
        $rules['contact_email'] = ['sometimes', 'nullable', 'email', 'max:255'];
        $rules['country'] = ['sometimes', 'nullable', 'string', 'max:80'];
        $rules['language'] = ['sometimes', 'nullable', 'string', 'max:80'];
        $rules['business_challenge'] = ['sometimes', 'nullable', 'string', 'max:8000'];
        $rules['birth_date'] = ['sometimes', 'nullable', 'date'];
        $rules['wants_to_deepen'] = ['sometimes', 'nullable', 'in:si,no'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_email.email' => __('El email del negocio no es válido.'),
            'contact_email.email' => __('El email de contacto no es válido.'),
        ];
    }
}
