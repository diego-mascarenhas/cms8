<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canAccessBilling();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(['hosting', 'service'])],
            'metadata' => ['present', 'array', 'max:50'],
            'metadata.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $metadata = $this->input('metadata', []);
            if (! is_array($metadata))
            {
                return;
            }

            foreach (array_keys($metadata) as $key)
            {
                if (! is_string($key) || preg_match('/^[A-Za-z0-9_]{1,40}$/', $key) !== 1)
                {
                    $validator->errors()->add('metadata', 'Las claves solo pueden tener letras, números y _.');

                    return;
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kind.required' => 'Elegí si es hosting o servicio.',
            'metadata.max' => 'Stripe acepta como máximo 50 campos.',
        ];
    }
}
