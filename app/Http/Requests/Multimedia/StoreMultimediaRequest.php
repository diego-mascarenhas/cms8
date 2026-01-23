<?php

namespace App\Http\Requests\Multimedia;

use App\Models\Multimedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMultimediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Multimedia::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:1,2',
            'visibility' => 'required|in:1,2',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:51200',
            'poster' => 'nullable|image|max:10240',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:50',
            'galleries' => 'nullable|array',
            'galleries.*' => 'nullable|string|max:50',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator)
        {
            $files = $this->file('files', []);
            if ($this->file('poster') && is_array($files) && count($files) > 1)
            {
                $validator->errors()->add('poster', 'Poster can only be used with a single file upload.');
            }
        });
    }
}
