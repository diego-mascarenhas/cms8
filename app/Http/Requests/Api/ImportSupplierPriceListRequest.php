<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportSupplierPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = (int) ($this->user()?->current_team_id ?? 0);

        return [
            'file' => ['required', 'file', 'max:15360'],
            'enterprise_id' => [
                'nullable',
                'integer',
                Rule::exists('enterprises', 'id')->where(function ($query) use ($teamId)
                {
                    $query->where('team_id', $teamId)->where('type_id', 2)->whereNull('deleted_at');
                }),
            ],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('status', 1)],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'name' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            $file = $this->file('file');
            if ($file === null)
            {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (! in_array($extension, ['csv', 'txt', 'xlsx', 'pdf', 'png', 'jpg', 'jpeg', 'webp'], true))
            {
                $validator->errors()->add('file', __('The file must be a CSV, spreadsheet, PDF, or image.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('Select a file to import.'),
            'enterprise_id.exists' => __('The selected supplier is invalid.'),
            'currency_id.required' => __('Please select a currency.'),
        ];
    }
}
