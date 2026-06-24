<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreList60Request extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->currentTeam?->hasModule('list60');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = auth()->user()->currentTeam->id;

        return [
            'contact_id' => [
                'required',
                'integer',
                Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('team_id', $teamId)),
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
