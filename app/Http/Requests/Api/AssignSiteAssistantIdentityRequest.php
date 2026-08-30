<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignSiteAssistantIdentityRequest extends FormRequest
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
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'create' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.integer' => __('Choose a contact to link.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void
        {
            $contactId = (int) $this->input('contact_id', 0);
            if ($contactId < 1 && ! $this->boolean('create'))
            {
                $validator->errors()->add('contact_id', __('Choose a contact or create a lead.'));
            }
        });
    }
}
