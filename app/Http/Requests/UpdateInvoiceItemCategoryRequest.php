<?php

namespace App\Http\Requests;

use App\Services\Finance\ServiceCategoryOptionsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInvoiceItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $categoryId = $this->input('category_id');

            if ($categoryId === null || $categoryId === '')
            {
                return;
            }

            $teamId = (int) auth()->user()->currentTeam->id;

            if (! app(ServiceCategoryOptionsService::class)->belongsToTeamServices($teamId, (int) $categoryId))
            {
                $validator->errors()->add('category_id', __('The selected category is invalid.'));
            }
        });
    }
}
