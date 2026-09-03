<?php

namespace App\Http\Requests\Api;

use App\Services\MailerCategoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailerCategorySortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sort' => ['required', 'string', Rule::in([
                MailerCategoryService::SORT_NAME,
                MailerCategoryService::SORT_MANUAL,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort.required' => __('Elegí un tipo de orden.'),
            'sort.in' => __('El orden debe ser alfabético o manual.'),
        ];
    }
}
