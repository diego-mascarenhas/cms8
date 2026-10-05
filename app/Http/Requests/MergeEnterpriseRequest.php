<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeEnterpriseRequest extends FormRequest
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
            'enterprise_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enterprise_id.required' => 'Elegí la empresa que querés fusionar.',
        ];
    }
}
