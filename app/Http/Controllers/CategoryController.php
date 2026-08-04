<?php

namespace App\Http\Controllers;

use App\DataTables\CategoryDataTable;
use App\Http\Requests\UpdateCategoryOrderRequest;
use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use App\Services\Finance\InvoicedLineItemsService;
use App\Services\Finance\ServiceCategoryOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Tags\Tag;

class CategoryController extends Controller
{
    public function index(CategoryDataTable $dataTable, Request $request)
    {
        // Get current team
        $team = Auth::user()->currentTeam;
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        // Require a module before loading the tree (avoids mixing categories from different modules).
        $moduleId = $request->filled('module_id') ? (int) $request->get('module_id') : null;

        $categories = collect();
        if ($moduleId !== null && $moduleId !== 0)
        {
            $categories = Category::where(function ($query) use ($team)
            {
                $query->whereNull('team_id')->orWhere('team_id', $team->id);
            })
                ->where('module_id', $moduleId)
                ->whereNull('parent_id')
                ->with(['children', 'module'])
                ->orderBy('order')
                ->orderBy('name')
                ->get();
        }

        // Get all modules for the filter dropdown
        $modules = Module::orderBy('name')->get();

        // Decide which view to use based on request
        if ($request->ajax())
        {
            return $dataTable->render('category.index');
        }

        return view('category.index', compact('categories', 'modules', 'moduleId', 'team'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Get current team
        $team = Auth::user()->currentTeam;
        if ($team && ! $team->relationLoaded('settings'))
        {
            $team->load('settings');
        }

        // Get parent_id if this is a subcategory
        $parentId = $request->get('parent_id');
        $parent = null;

        if ($parentId)
        {
            $parent = Category::where('team_id', $team->id)->findOrFail($parentId);
        }

        // Get modules for dropdown
        $modules = Module::orderBy('name')->get();
        $multimediaModuleId = Module::where('key', 'multimedia')->value('id');

        $selectedModuleId = old('module_id', $request->get('module_id'));
        if ($parent)
        {
            $selectedModuleId = $selectedModuleId ?: $parent->module_id;
        }
        $selectedModuleId = $selectedModuleId ? (int) $selectedModuleId : null;

        $parentCategoriesByModule = $this->parentCategoriesGroupedByModule($team->id);
        $parentCategories = $this->withNestedParentAppendedToParentOptions(
            $this->topLevelParentsForTeamModule($team->id, $selectedModuleId),
            $parent,
        );

        // Get tags for autocomplete
        $tags = Tag::getWithType('general')->sortBy('name')->values();

        $returnModuleIdForIndex = $request->filled('module_id') ? (int) $request->get('module_id') : null;

        return view('category.form', compact('modules', 'parentCategories', 'parentCategoriesByModule', 'parent', 'team', 'multimediaModuleId', 'tags', 'returnModuleIdForIndex'));
    }

    public function duplicate(Request $request, int $id): RedirectResponse
    {
        $team = Auth::user()->currentTeam;

        $category = Category::query()
            ->where(function ($query) use ($team)
            {
                $query->whereNull('team_id')->orWhere('team_id', $team->id);
            })
            ->findOrFail($id);

        $data = is_array($category->data ?? null) ? $category->data : [];
        $coverData = is_array($data['cover'] ?? null) ? $data['cover'] : [];
        $coverVariants = is_array($coverData['variants'] ?? null) ? $coverData['variants'] : [];

        $presetVariantKeys = ['logo_strip', 'thumb', 'hero', 'square', 'og', 'web'];
        $selectedVariants = [];
        $variantWidths = [];
        $variantHeights = [];
        $variantFits = [];

        foreach ($coverVariants as $variantKey => $variantCfg)
        {
            if (! is_string($variantKey) || ! is_array($variantCfg))
            {
                continue;
            }

            $safeKey = in_array($variantKey, $presetVariantKeys, true) ? $variantKey : 'custom';
            if (! in_array($safeKey, $selectedVariants, true))
            {
                $selectedVariants[] = $safeKey;
            }

            if ($safeKey !== 'custom')
            {
                $variantWidths[$safeKey] = $variantCfg['width'] ?? null;
                $variantHeights[$safeKey] = $variantCfg['height'] ?? null;
                $variantFits[$safeKey] = $variantCfg['fit'] ?? 'max';
            }
        }

        $customVariant = is_array($coverVariants['custom'] ?? null)
            ? $coverVariants['custom']
            : collect($coverVariants)->first(fn ($v, $k) => is_string($k) && ! in_array($k, $presetVariantKeys, true) && is_array($v));

        $input = [
            'name' => $category->name.' (Copy)',
            'status' => $category->status ? '1' : '0',
            'module_id' => $category->module_id,
            'parent_id' => $category->parent_id,
            'description' => $category->description,
            'order' => $category->order ?? 0,
            'cover_max_width' => $coverData['max_width'] ?? null,
            'cover_max_height' => $coverData['max_height'] ?? null,
            'cover_variants' => $selectedVariants,
            'cover_variant_width' => $variantWidths,
            'cover_variant_height' => $variantHeights,
            'cover_variant_fit' => $variantFits,
            'cover_custom_variant_width' => is_array($customVariant) ? ($customVariant['width'] ?? null) : null,
            'cover_custom_variant_height' => is_array($customVariant) ? ($customVariant['height'] ?? null) : null,
            'cover_custom_variant_fit' => is_array($customVariant) ? ($customVariant['fit'] ?? 'max') : 'max',
            'image_width' => $data['image_width'] ?? null,
            'image_height' => $data['image_height'] ?? null,
            'thumb_width' => $data['thumb_width'] ?? null,
            'thumb_height' => $data['thumb_height'] ?? null,
            'poster_width' => $data['poster_width'] ?? null,
            'poster_height' => $data['poster_height'] ?? null,
            'fit' => $data['fit'] ?? null,
            'return_module_id' => $category->module_id,
        ];

        return redirect()
            ->route('categories.create', ['module_id' => $category->module_id])
            ->withInput($input);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:255',
            'module_id' => 'nullable|exists:modules,id',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0|max:255',
            'image_width' => 'nullable|integer|min:1|max:10000',
            'image_height' => 'nullable|integer|min:1|max:10000',
            'thumb_width' => 'nullable|integer|min:1|max:10000',
            'thumb_height' => 'nullable|integer|min:1|max:10000',
            'poster_width' => 'nullable|integer|min:1|max:10000',
            'poster_height' => 'nullable|integer|min:1|max:10000',
            'fit' => 'nullable|in:crop,contain,max,stretch',
            'return_module_id' => 'nullable|integer|exists:modules,id',
            'contents_section_slug' => 'nullable|string|max:100',
            'history_section_heading' => 'nullable|string|max:255',
            'cover_max_width' => 'nullable|integer|min:1|max:10000',
            'cover_max_height' => 'nullable|integer|min:1|max:10000',
            'cover_crop' => 'nullable|boolean',
            'cover_variants' => 'nullable|array',
            'cover_variants.*' => 'nullable|string|in:logo_strip,thumb,hero,square,og,web,custom',
            'cover_variant_fit' => 'nullable|array',
            'cover_variant_fit.*' => 'nullable|string|in:crop,contain,max,stretch',
            'cover_variant_width' => 'nullable|array',
            'cover_variant_width.*' => 'nullable|integer|min:1|max:10000',
            'cover_variant_height' => 'nullable|array',
            'cover_variant_height.*' => 'nullable|integer|min:1|max:10000',
            'cover_custom_variant_key' => 'nullable|string|max:50',
            'cover_custom_variant_width' => 'nullable|integer|min:1|max:10000',
            'cover_custom_variant_height' => 'nullable|integer|min:1|max:10000',
            'cover_custom_variant_fit' => 'nullable|string|in:crop,contain,max,stretch',
        ]);

        // Check if parent belongs to the current team
        if ($request->parent_id)
        {
            $parent = Category::where('team_id', $team->id)->findOrFail($request->parent_id);

            if ($request->filled('module_id') && (int) $parent->module_id !== (int) $request->module_id)
            {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', __('app.Parent category must belong to the same module.'));
            }

            // Check maximum depth allowed
            $maxDepth = (int) $team->getSetting('categories_max_depth', 2);
            $currentDepth = $this->getCategoryDepth($parent);

            if ($currentDepth >= $maxDepth)
            {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Maximum category depth of {$maxDepth} levels reached.");
            }
        }

        $category = new Category;
        if ($request->id)
        {
            $category = Category::where('team_id', $team->id)->findOrFail($request->id);
        }

        $categoryData = $category->data ?? [];
        $module = $request->module_id ? Module::find($request->module_id) : null;

        if ($module && $module->key === 'multimedia')
        {
            $categoryData = array_merge($categoryData, array_filter([
                'image_width' => $request->input('image_width') ? (int) $request->input('image_width') : null,
                'image_height' => $request->input('image_height') ? (int) $request->input('image_height') : null,
                'thumb_width' => $request->input('thumb_width') ? (int) $request->input('thumb_width') : null,
                'thumb_height' => $request->input('thumb_height') ? (int) $request->input('thumb_height') : null,
                'poster_width' => $request->input('poster_width') ? (int) $request->input('poster_width') : null,
                'poster_height' => $request->input('poster_height') ? (int) $request->input('poster_height') : null,
                'fit' => $request->input('fit') ?: null,
            ], function ($value)
            {
                return ! is_null($value);
            }));
        }

        $category->fill([
            'name' => $request->name,
            'description' => $request->description,
            'module_id' => $request->module_id,
            'parent_id' => $request->parent_id,
            'order' => $request->order ?? 0,
            'status' => $request->has('status') ? 1 : 0,
            'team_id' => $team->id,
            'data' => $categoryData,
        ]);

        $category->save();

        // Sync tags
        if ($request->has('tags') && is_array($request->tags))
        {
            $normalizedTags = array_map(function ($tagName)
            {
                return trim($tagName);
            }, array_filter($request->tags));
            $category->syncTagsWithType($normalizedTags, 'general');
        } else
        {
            $category->syncTagsWithType([], 'general');
        }

        return $this->redirectToCategoriesIndexAfterSave($request, $category);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)
            ->with(['children', 'parent', 'module'])
            ->findOrFail($id);

        return view('category.show', compact('category'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)->findOrFail($id);

        // Get modules for dropdown
        $modules = Module::orderBy('name')->get();
        $multimediaModuleId = Module::where('key', 'multimedia')->value('id');

        // Get possible parent categories for dropdown, excluding self and descendants
        $excludeIds = $this->getAllChildrenIds($category);
        $excludeIds[] = $category->id;

        $selectedModuleId = old('module_id', $category->module_id);
        $selectedModuleId = $selectedModuleId ? (int) $selectedModuleId : null;

        $parentCategoriesByModule = $this->parentCategoriesGroupedByModule($team->id, $excludeIds);
        $category->loadMissing('parent');
        $parentCategories = $this->withNestedParentAppendedToParentOptions(
            $this->topLevelParentsForTeamModule($team->id, $selectedModuleId, $excludeIds),
            $category->parent,
        );

        // Get tags for autocomplete
        $tags = Tag::getWithType('general')->sortBy('name')->values();

        // Parent is not needed in edit, only in create when creating a subcategory
        $parent = null;

        $returnModuleIdForIndex = $request->filled('module_id') ? (int) $request->get('module_id') : null;

        return view('category.form', compact('category', 'modules', 'parentCategories', 'parentCategoriesByModule', 'parent', 'team', 'multimediaModuleId', 'tags', 'returnModuleIdForIndex'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate and store will handle this via updateOrCreate
        return $this->store($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)->findOrFail($id);

        // Check if category is being used
        $usageCount = $this->getCategoryUsageCount($category);

        if ($usageCount > 0)
        {
            return response()->json([
                'error' => "This category is being used by {$usageCount} items and cannot be deleted.",
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => 'Category deleted successfully.'], 200);
    }

    /**
     * Toggle category active flag (shown in hierarchy list).
     */
    public function toggleStatus(string $id)
    {
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)->findOrFail($id);

        $this->authorize('update', $category);

        $category->status = ! $category->status;
        $category->save();

        return response()->json([
            'success' => true,
            'status' => $category->status ? 1 : 0,
            'message' => __('app.Category status updated'),
        ]);
    }

    /**
     * Update the order of categories.
     */
    public function updateOrder(UpdateCategoryOrderRequest $request): JsonResponse
    {
        $team = Auth::user()->currentTeam;
        $moduleId = (int) $request->validated('module_id');

        Category::query()->getModel()->getConnection()->transaction(function () use ($request, $team, $moduleId): void
        {
            foreach ($request->validated('categories') as $item)
            {
                $parentRaw = $item['parent_id'] ?? null;
                $parentId = ($parentRaw === null || $parentRaw === '' || $parentRaw === false || $parentRaw === 0 || $parentRaw === '0')
                    ? null
                    : (int) $parentRaw;

                Category::query()
                    ->where('module_id', $moduleId)
                    ->where(function ($query) use ($team): void
                    {
                        $query->whereNull('team_id')
                            ->orWhere('team_id', $team->id);
                    })
                    ->where('id', (int) $item['id'])
                    ->update([
                        'parent_id' => $parentId,
                        'order' => (int) $item['order'],
                    ]);
            }
        });

        return response()->json(['success' => __('app.Order updated successfully.')], 200);
    }

    /**
     * Get report of category usage.
     */
    public function report()
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $categories = Category::where('team_id', $team->id)
            ->with(['invoiceItems.invoice.enterprise'])
            ->get();

        $uncategorizedItems = InvoiceItem::with(['invoice.enterprise'])
            ->whereNull('category_id')
            ->get();

        $reportData = $categories->map(function ($category)
        {
            $sortedItems = $category->invoiceItems->sortByDesc(function ($item)
            {
                return $item->quantity * $item->unit_price - ($item->discount ?? 0);
            });

            $totalAmount = $sortedItems->sum(function ($item)
            {
                return $item->quantity * $item->unit_price - ($item->discount ?? 0);
            });

            if ($totalAmount == 0)
            {
                return null;
            }

            $operation = $sortedItems->first()->invoice->operation ?? 'Unknown';
            $labelClass = ($operation === 'sell') ? 'bg-success' : 'bg-danger';

            return [
                'id' => $category->id,
                'category' => $category->name,
                'description' => $category->description,
                'items' => $sortedItems,
                'total' => $totalAmount,
                'labelClass' => $labelClass,
            ];
        })->filter();

        return view('category.report', compact('reportData'));
    }

    /**
     * Show items in a category.
     */
    public function showItems(Request $request, InvoicedLineItemsService $invoicedLineItemsService, $id)
    {
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)->findOrFail($id);

        $operation = (string) $request->query('operation', '');
        if ($operation !== '' && ! in_array($operation, ['sell', 'buy'], true))
        {
            abort(404);
        }
        $operation = $operation !== '' ? $operation : null;

        $period = $invoicedLineItemsService->resolvePeriodFilter($request);
        $items = $invoicedLineItemsService->queryItems(
            teamId: (int) $team->id,
            from: $period['from'],
            to: $period['to'],
            operation: $operation,
            categoryId: (int) $category->id,
        );

        $canEditCategory = (bool) $request->user()?->hasRole('admin');

        $display = $invoicedLineItemsService->buildDisplayPayload(
            $items,
            (int) $team->id,
            showDescription: false,
            showCategory: $canEditCategory,
        );

        return view('category.items', [
            'category' => $category,
            'lines' => $display['lines'],
            'totalAmount' => $display['total'],
            'reportingCurrency' => $display['reporting_currency'],
            'conversionComplete' => $display['conversion_complete'],
            'availableYears' => $invoicedLineItemsService->availableYearsForTeam((int) $team->id),
            'selectedYear' => $period['year'],
            'selectedMonth' => $period['month'],
            'operation' => $operation,
            'amountTone' => $operation === 'sell' ? 'income' : ($operation === 'buy' ? 'expense' : 'auto'),
            'backUrl' => $this->resolveCategoryItemsBackUrl($request),
            'canEditCategory' => $canEditCategory,
            'categoryOptions' => $canEditCategory
                ? app(ServiceCategoryOptionsService::class)->optionsForTeam((int) $team->id)
                : [],
        ]);
    }

    private function resolveCategoryItemsBackUrl(Request $request): string
    {
        $return = (string) $request->query('return', '');

        if ($return !== '' && str_starts_with($return, (string) config('app.url')))
        {
            return $return;
        }

        return route('finance-dashboard.index');
    }

    /**
     * Get the depth of a category in the hierarchy.
     */
    private function getCategoryDepth($category)
    {
        $depth = 0;
        $current = $category;

        while ($current->parent_id)
        {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    /**
     * Get all children IDs of a category (for exclusion in parent dropdowns).
     */
    private function getAllChildrenIds($category)
    {
        $ids = [];

        foreach ($category->children as $child)
        {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllChildrenIds($child));
        }

        return $ids;
    }

    /**
     * Top-level categories eligible as parent, for the same module only.
     *
     * @param  array<int>  $excludeIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    private function topLevelParentsForTeamModule(int $teamId, ?int $moduleId, array $excludeIds = [])
    {
        $query = Category::query()
            ->where('team_id', $teamId)
            ->whereNull('parent_id')
            ->whereNotNull('module_id')
            ->orderBy('name');

        if ($excludeIds !== [])
        {
            $query->whereNotIn('id', $excludeIds);
        }

        if ($moduleId !== null && $moduleId !== 0)
        {
            $query->where('module_id', $moduleId);
        } else
        {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    /**
     * The parent select is built from top-level categories only; append the resolved parent
     * when it is nested so "create under this row" and edit keep a valid selected value.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Category>  $topLevelParents
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    private function withNestedParentAppendedToParentOptions($topLevelParents, ?Category $resolvedParent)
    {
        if ($resolvedParent === null)
        {
            return $topLevelParents;
        }

        if ($topLevelParents->contains(fn (Category $c): bool => (int) $c->id === (int) $resolvedParent->id))
        {
            return $topLevelParents;
        }

        return $topLevelParents->concat([$resolvedParent]);
    }

    /**
     * Parent options keyed by module id (string) for the category form JavaScript.
     *
     * @param  array<int>  $excludeIds
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function parentCategoriesGroupedByModule(int $teamId, array $excludeIds = []): array
    {
        $query = Category::query()
            ->where('team_id', $teamId)
            ->whereNull('parent_id')
            ->whereNotNull('module_id')
            ->orderBy('name');

        if ($excludeIds !== [])
        {
            $query->whereNotIn('id', $excludeIds);
        }

        $grouped = [];
        foreach ($query->get(['id', 'name', 'module_id']) as $category)
        {
            $key = (string) $category->module_id;
            $grouped[$key][] = ['id' => $category->id, 'name' => $category->name];
        }

        return $grouped;
    }

    /**
     * Quick store category from select2 (AJAX)
     */
    public function quickStore(Request $request)
    {
        $team = Auth::user()->currentTeam;

        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'module_key' => 'nullable|string',
        ]);

        // Get module if module_key is provided
        $module = null;
        if ($request->module_key)
        {
            $module = Module::where('key', $request->module_key)->first();
        }

        $moduleId = $module ? $module->id : null;
        $normalizedName = mb_strtolower(trim($request->name));

        $existingQuery = Category::query()
            ->where('team_id', $team->id)
            ->whereNull('parent_id')
            ->where('status', 1);

        if ($moduleId !== null)
        {
            $existingQuery->where('module_id', $moduleId);
        } else
        {
            $existingQuery->whereNull('module_id');
        }

        $existing = $existingQuery->get()->first(function (Category $category) use ($normalizedName)
        {
            return mb_strtolower(trim($category->name)) === $normalizedName;
        });

        if ($existing)
        {
            return response()->json([
                'success' => true,
                'existing' => true,
                'category' => [
                    'id' => $existing->id,
                    'name' => $existing->name,
                ],
            ]);
        }

        $category = Category::create([
            'name' => $request->name,
            'module_id' => $moduleId,
            'parent_id' => null, // Quick create as parent category
            'order' => 0,
            'status' => 1,
            'team_id' => $team->id,
        ]);

        return response()->json([
            'success' => true,
            'existing' => false,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ]);
    }

    /**
     * Count how many items are using this category.
     */
    private function getCategoryUsageCount($category)
    {
        return $category->blockingDeleteUsageCount();
    }

    /**
     * JSON structure for rebuilding module category &lt;select&gt; (parents, optgroups, children).
     */
    public function moduleOptions(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $team = Auth::user()->currentTeam;

        $validated = $request->validate([
            'module_key' => 'required|string',
        ]);

        $module = Module::where('key', $validated['module_key'])->firstOrFail();

        $baseQuery = Category::query()
            ->where('module_id', $module->id)
            ->where('status', '>', 0)
            ->where(function ($query) use ($team)
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', $team->id);
            });

        $parentCategories = (clone $baseQuery)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $allSubcategories = (clone $baseQuery)
            ->whereNotNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        $groups = [];
        $parentIds = $parentCategories->pluck('id')->map(fn ($id) => (int) $id)->all();
        $categoryById = (clone $baseQuery)->get(['id', 'name', 'parent_id'])->keyBy('id');

        $nestedLabel = function (Category $category) use ($categoryById): string
        {
            $parts = [(string) $category->name];
            $parentId = $category->parent_id ? (int) $category->parent_id : null;
            $guard = 0;

            while ($parentId && $guard < 5)
            {
                $parent = $categoryById->get($parentId);
                if (! $parent)
                {
                    break;
                }

                if ($parent->parent_id === null)
                {
                    break;
                }

                array_unshift($parts, (string) $parent->name);
                $parentId = $parent->parent_id ? (int) $parent->parent_id : null;
                $guard++;
            }

            return implode(' › ', $parts);
        };

        $renderedIds = [];

        foreach ($parentCategories as $parentCategory)
        {
            $subs = collect($allSubcategories[$parentCategory->id] ?? []);
            $nested = collect();

            foreach ($subs as $child)
            {
                foreach (collect($allSubcategories[$child->id] ?? []) as $grandChild)
                {
                    $nested->push($grandChild);
                }
            }

            if ($subs->isEmpty() && $nested->isEmpty())
            {
                $groups[] = [
                    'type' => 'option',
                    'id' => $parentCategory->id,
                    'label' => $parentCategory->name,
                ];
                $renderedIds[(int) $parentCategory->id] = true;

                continue;
            }

            $options = $subs->map(function (Category $c) use (&$renderedIds)
            {
                $renderedIds[(int) $c->id] = true;

                return [
                    'id' => $c->id,
                    'label' => $c->name,
                ];
            })->values();

            foreach ($nested as $nestedCategory)
            {
                $renderedIds[(int) $nestedCategory->id] = true;
                $options->push([
                    'id' => $nestedCategory->id,
                    'label' => $nestedLabel($nestedCategory),
                ]);
            }

            $groups[] = [
                'type' => 'group',
                'label' => $parentCategory->name,
                'options' => $options->all(),
            ];
        }

        foreach ($allSubcategories as $parentId => $subs)
        {
            if (in_array((int) $parentId, $parentIds, true))
            {
                continue;
            }

            $parentCategory = $categoryById->get((int) $parentId);
            $skipAsNested = $parentCategory
                && $parentCategory->parent_id !== null
                && in_array((int) $parentCategory->parent_id, $parentIds, true);

            if ($skipAsNested)
            {
                continue;
            }

            foreach ($subs as $category)
            {
                if (isset($renderedIds[(int) $category->id]))
                {
                    continue;
                }

                $groups[] = [
                    'type' => 'option',
                    'id' => $category->id,
                    'label' => $nestedLabel($category),
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }

    private function redirectToCategoriesIndexAfterSave(Request $request, Category $category): RedirectResponse
    {
        $returnModuleId = (int) $request->input('return_module_id', 0);
        if ($returnModuleId > 0 && Module::query()->whereKey($returnModuleId)->exists())
        {
            return redirect()
                ->route('categories.index', ['module_id' => $returnModuleId])
                ->with('success', 'Category saved successfully.');
        }

        if ($category->module_id)
        {
            return redirect()
                ->route('categories.index', ['module_id' => (int) $category->module_id])
                ->with('success', 'Category saved successfully.');
        }

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category saved successfully.');
    }
}
