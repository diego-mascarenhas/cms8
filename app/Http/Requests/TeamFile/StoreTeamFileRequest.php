<?php

namespace App\Http\Requests\TeamFile;

use App\Enums\MultimediaVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\TeamFile::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['required', 'integer', Rule::in(array_column(MultimediaVisibility::cases(), 'value'))],
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,txt,xls,xlsx,ppt,pptx,jpeg,jpg,png,gif,webp,svg',
        ];
    }
}
