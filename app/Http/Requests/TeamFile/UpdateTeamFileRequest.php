<?php

namespace App\Http\Requests\TeamFile;

use App\Enums\MultimediaVisibility;
use App\Http\Requests\TeamFile\Concerns\ValidatesTeamFileCategory;
use App\Rules\DisallowsDangerousTeamFileUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamFileRequest extends FormRequest
{
    use ValidatesTeamFileCategory;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('team_file'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => $this->teamFileCategoryIdRules(),
            'visibility' => ['required', 'integer', Rule::in(array_column(MultimediaVisibility::cases(), 'value'))],
            'file' => ['nullable', 'file', 'max:10240', new DisallowsDangerousTeamFileUpload],
        ];
    }
}
