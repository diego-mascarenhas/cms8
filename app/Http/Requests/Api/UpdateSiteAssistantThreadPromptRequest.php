<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteAssistantThreadPromptRequest extends FormRequest
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
            'on' => ['sometimes', 'boolean'],
            'prompt_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'on.required' => __('Indicate whether the assistant should reply.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void
        {
            if (! $this->exists('on') && ! $this->exists('prompt_key'))
            {
                $validator->errors()->add('on', __('Indicate whether the assistant should reply.'));
            }
        });
    }
}
