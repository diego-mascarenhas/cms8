<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->currentTeam !== null;
    }

    protected function prepareForValidation(): void
    {
        $categories = $this->input('categories');
        if (! is_array($categories))
        {
            return;
        }

        foreach ($categories as $index => $row)
        {
            if (! is_array($row))
            {
                continue;
            }
            if (! array_key_exists('parent_id', $row))
            {
                continue;
            }
            $parent = $row['parent_id'];
            if ($parent === '' || $parent === false || $parent === 0 || $parent === '0')
            {
                $categories[$index]['parent_id'] = null;
            }
        }

        $this->merge(['categories' => $categories]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'module_id' => 'required|integer|exists:modules,id',
            'categories' => 'required|array|min:1',
            'categories.*.id' => 'required|integer|exists:categories,id',
            'categories.*.order' => 'required|integer|min:0',
            'categories.*.parent_id' => 'nullable|integer|exists:categories,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void
        {
            if ($validator->errors()->isNotEmpty())
            {
                return;
            }

            $team = $this->user()?->currentTeam;
            if (! $team)
            {
                $validator->errors()->add('module_id', __('app.Failed to update category order'));

                return;
            }

            $moduleId = (int) $this->input('module_id');
            $allowedIds = Category::query()
                ->where('module_id', $moduleId)
                ->where(function ($query) use ($team): void
                {
                    $query->whereNull('team_id')
                        ->orWhere('team_id', $team->id);
                })
                ->pluck('id')
                ->all();

            $allowedIdSet = array_flip($allowedIds);

            foreach ($this->input('categories', []) as $index => $item)
            {
                $id = (int) ($item['id'] ?? 0);
                if (! isset($allowedIdSet[$id]))
                {
                    $validator->errors()->add('categories.'.$index.'.id', __('app.Failed to update category order'));

                    continue;
                }

                $parentId = $this->normalizedParentId($item['parent_id'] ?? null);

                if ($parentId !== null)
                {
                    if (! isset($allowedIdSet[$parentId]) || $parentId === $id)
                    {
                        $validator->errors()->add('categories.'.$index.'.parent_id', __('app.Failed to update category order'));
                    }
                }
            }
        });
    }

    /**
     * @param  mixed  $value
     */
    private function normalizedParentId($value): ?int
    {
        if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0')
        {
            return null;
        }

        return (int) $value;
    }
}
