<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Module;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::query()->select('id', 'name')->orderBy('name');

        $teamId = $request->user()?->currentTeam?->id;
        if ($teamId)
        {
            $query->where(function ($builder) use ($teamId)
            {
                $builder->whereNull('team_id')->orWhere('team_id', $teamId);
            });
        }

        $moduleKey = trim((string) $request->get('module_key', ''));
        if ($moduleKey !== '')
        {
            $moduleId = Module::query()->where('key', $moduleKey)->value('id');
            if (! $moduleId)
            {
                return CategoryResource::collection(collect());
            }

            $query->where('module_id', $moduleId)->where('status', '>', 0);
        }

        return CategoryResource::collection($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
