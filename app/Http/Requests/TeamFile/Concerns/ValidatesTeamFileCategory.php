<?php

namespace App\Http\Requests\TeamFile\Concerns;

use App\Models\Module;
use Illuminate\Validation\Rule;

trait ValidatesTeamFileCategory
{
    protected function prepareForValidation(): void
    {
        if ($this->has('category_id') && $this->input('category_id') === '')
        {
            $this->merge(['category_id' => null]);
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function teamFileCategoryIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('categories', 'id')->where(function ($query)
            {
                $teamId = $this->user()->currentTeam->id;
                $query->where(function ($q) use ($teamId)
                {
                    $q->whereNull('team_id')->orWhere('team_id', $teamId);
                });
                $moduleId = Module::query()->where('key', 'team_files')->value('id');
                if ($moduleId !== null)
                {
                    $query->where('module_id', $moduleId);
                }
            }),
        ];
    }
}
