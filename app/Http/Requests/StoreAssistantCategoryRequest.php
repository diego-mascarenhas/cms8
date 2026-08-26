<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssistantCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'color' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter a category name.'),
            'name.min' => __('Enter a category name.'),
            'color.regex' => __('El color debe ser un hexadecimal de 6 cifras, por ejemplo #c4a574.'),
        ];
    }
}
