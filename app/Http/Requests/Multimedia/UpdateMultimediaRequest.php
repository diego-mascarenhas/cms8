<?php

namespace App\Http\Requests\Multimedia;

use App\Models\Multimedia;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMultimediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $multimedia = $this->route('multimedia');

        return $multimedia instanceof Multimedia
            ? ($this->user()?->can('update', $multimedia) ?? false)
            : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:1,2',
            'visibility' => 'required|in:1,2',
            'media' => 'nullable|file|max:51200',
            'poster' => 'nullable|image|max:10240',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:50',
            'galleries' => 'nullable|array',
            'galleries.*' => 'nullable|string|max:50',
        ];
    }
}
