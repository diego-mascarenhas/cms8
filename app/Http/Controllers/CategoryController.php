<?php

namespace App\Http\Controllers;

use App\DataTables\CategoryDataTable;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\Team;
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

        // Get module filter if set
        $moduleId = $request->get('module_id');

        // Get only parent categories (null parent_id): global (team_id null) or belonging to current team
        $categories = Category::where(function ($query) use ($team)
        {
            $query->whereNull('team_id')->orWhere('team_id', $team->id);
        })
            ->when($moduleId, function ($query, $moduleId)
            {
                return $query->where('module_id', $moduleId);
            })
            ->whereNull('parent_id')
            ->with(['children', 'module'])
            ->orderBy('order')
            ->orderBy('name')
            ->get();

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

        // Get possible parent categories for dropdown
        $parentCategories = Category::where('team_id', $team->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        // Get tags for autocomplete
        $tags = Tag::getWithType('general')->sortBy('name')->values();

        return view('category.form', compact('modules', 'parentCategories', 'parent', 'team', 'multimediaModuleId', 'tags'));
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
        ]);

        // Check if parent belongs to the current team
        if ($request->parent_id)
        {
            $parent = Category::where('team_id', $team->id)->findOrFail($request->parent_id);

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

        if ($module && $module->key === 'contents')
        {
            // Handle content ordering configuration
            $contentOrdering = [];
            if ($request->has('content_ordering') && is_array($request->input('content_ordering')))
            {
                foreach ($request->input('content_ordering') as $ordering)
                {
                    if (! empty($ordering['column']) && ! empty($ordering['direction']))
                    {
                        $contentOrdering[] = [
                            'column' => $ordering['column'],
                            'direction' => $ordering['direction'],
                        ];
                    }
                }
            }

            // If no custom ordering, use default
            if (empty($contentOrdering))
            {
                $contentOrdering = [
                    ['column' => 'order', 'direction' => 'asc'],
                    ['column' => 'created_at', 'direction' => 'desc'],
                ];
            }

            $categoryData['content_ordering'] = $contentOrdering;
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

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category saved successfully.');
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
    public function edit(string $id)
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

        $parentCategories = Category::where('team_id', $team->id)
            ->whereNull('parent_id')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();

        // Get tags for autocomplete
        $tags = Tag::getWithType('general')->sortBy('name')->values();

        // Parent is not needed in edit, only in create when creating a subcategory
        $parent = null;

        return view('category.form', compact('category', 'modules', 'parentCategories', 'parent', 'team', 'multimediaModuleId', 'tags'));
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
     * Update the order of categories.
     */
    public function updateOrder(Request $request)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->categories as $item)
        {
            Category::where('team_id', $team->id)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['success' => 'Order updated successfully.'], 200);
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
    public function showItems($id)
    {
        // Get current team
        $team = Auth::user()->currentTeam;

        $category = Category::where('team_id', $team->id)
            ->with('invoiceItems.invoice.enterprise')
            ->findOrFail($id);

        $totalAmount = $category->invoiceItems->sum(function ($item)
        {
            return $item->quantity * $item->unit_price - ($item->discount ?? 0);
        });

        return view('category.items', [
            'category' => $category,
            'items' => $category->invoiceItems,
            'totalAmount' => $totalAmount,
        ]);
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
            ->where('status', 1)
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
        foreach ($parentCategories as $parentCategory)
        {
            $subs = $allSubcategories[$parentCategory->id] ?? null;
            if (! $subs || $subs->isEmpty())
            {
                $groups[] = [
                    'type' => 'option',
                    'id' => $parentCategory->id,
                    'label' => $parentCategory->name,
                ];
            } else
            {
                $groups[] = [
                    'type' => 'group',
                    'label' => $parentCategory->name,
                    'options' => $subs->map(function (Category $c)
                    {
                        return [
                            'id' => $c->id,
                            'label' => $c->name,
                        ];
                    })->values()->all(),
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }
}
