<?php

namespace App\Http\Requests;

use App\Enums\PaidAdObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class SuggestPaidAdImageRequest extends FormRequest
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
            'goal' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'objective' => ['nullable', new Enum(PaidAdObjective::class)],
            'locations' => ['nullable', 'string', 'max:1000'],
            'platforms' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'objective.Illuminate\Validation\Rules\Enum' => __('Elegí un objetivo válido.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $context = trim(implode(' ', array_filter([
                (string) $this->input('goal', ''),
                (string) $this->input('name', ''),
                (string) $this->input('headline', ''),
                (string) $this->input('body', ''),
            ])));

            if (mb_strlen($context) < 8)
            {
                $validator->errors()->add(
                    'headline',
                    __('Completá el titular, el texto o el nombre para sugerir una imagen.'),
                );
            }
        });
    }
}
