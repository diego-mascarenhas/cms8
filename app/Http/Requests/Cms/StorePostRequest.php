<?php

namespace App\Http\Requests\Cms;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Post::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()?->currentTeam?->id;

        return [
            'post_type' => [
                'required',
                'string',
                'max:50',
                Rule::exists('post_types', 'name')->where(fn ($q) => $q->where('team_id', $teamId)),
            ],
            'post_title' => ['nullable', 'string', 'max:255'],
            'post_name' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9\-]*$/'],
            'post_content' => ['nullable', 'string'],
            'post_excerpt' => ['nullable', 'string'],
            'post_status' => ['required', Rule::in([
                Post::STATUS_PUBLISH,
                Post::STATUS_DRAFT,
                Post::STATUS_PENDING,
                Post::STATUS_FUTURE,
                Post::STATUS_PRIVATE,
            ])],
            'post_parent' => ['nullable', 'integer', 'min:0'],
            'menu_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'terms' => ['nullable', 'array'],
            'terms.*' => ['integer'],
            'category_terms' => ['nullable', 'array'],
            'category_terms.*' => ['integer'],
            'tag_terms' => ['nullable', 'array'],
            'tag_terms.*' => ['integer'],
            'new_category' => ['nullable', 'array'],
            'new_category.name' => ['nullable', 'string', 'max:255'],
            'new_category.parent' => ['nullable', 'integer', 'min:0'],
            'new_tags' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
