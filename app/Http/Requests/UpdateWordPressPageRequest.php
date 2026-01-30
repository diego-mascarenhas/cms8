<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWordPressPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'nullable|string|in:publish,draft,pending,private',
        ];
    }
}
