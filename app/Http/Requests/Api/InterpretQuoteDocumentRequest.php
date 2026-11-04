<?php

namespace App\Http\Requests\Api;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InterpretQuoteDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:15360'],
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
}
