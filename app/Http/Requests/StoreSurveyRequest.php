<?php

namespace App\Http\Requests;

use App\Models\Survey;
use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Survey::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array'],
            'fields.*.key' => ['nullable', 'string', 'max:100'],
            'fields.*.label' => ['nullable', 'string', 'max:2000'],
            'fields.*.help' => ['nullable', 'string', 'max:2000'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.anonymous' => ['nullable', 'boolean'],
            'fields.*.for_certificate' => ['nullable', 'boolean'],
            'fields.*.options_text' => ['nullable', 'string'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*.label' => ['nullable', 'string', 'max:500'],
            'fields.*.options.*.value' => ['nullable', 'string', 'max:100'],
        ];
    }
}
