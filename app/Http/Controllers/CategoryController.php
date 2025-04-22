<?php

namespace App\Http\Controllers;

use App\DataTables\CategoryDataTable;
use App\Models\Category;
use App\Models\Module;
use App\Models\Team;
use Illuminate\Http\Request;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index(CategoryDataTable $dataTable, Request $request)
    {
        // Get current team
        $team = Auth::user()->currentTeam;
        
        // Get module filter if set
        $moduleId = $request->get('module_id');
        
        // Get only parent categories (null parent_id) to show in a hierarchical view
        $categories = Category::where('team_id', $team->id)
            ->when($moduleId, function($query, $moduleId) {
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
        if ($request->ajax()) {
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
        
        // Get parent_id if this is a subcategory
        $parentId = $request->get('parent_id');
        $parent = null;
        
        if ($parentId) {
            $parent = Category::where('team_id', $team->id)->findOrFail($parentId);
        }
        
        // Get modules for dropdown
        $modules = Module::orderBy('name')->get();
        
        // Get possible parent categories for dropdown
        $parentCategories = Category::where('team_id', $team->id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
        
        return view('category.form', compact('modules', 'parentCategories', 'parent', 'team'));
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
        ]);
        
        // Check if parent belongs to the current team
        if ($request->parent_id) {
            $parent = Category::where('team_id', $team->id)->findOrFail($request->parent_id);
            
            // Check maximum depth allowed
            $maxDepth = (int) $team->getSetting('categories_max_depth', 2);
            $currentDepth = $this->getCategoryDepth($parent);
            
            if ($currentDepth >= $maxDepth) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Maximum category depth of {$maxDepth} levels reached.");
            }
        }
        
        $category = new Category();
        if ($request->id) {
            $category = Category::where('team_id', $team->id)->findOrFail($request->id);
        }
        
        $category->fill([
            'name' => $request->name,
            'description' => $request->description,
            'module_id' => $request->module_id,
            'parent_id' => $request->parent_id,
            'order' => $request->order ?? 0,
            'status' => $request->has('status') ? 1 : 0,
            'team_id' => $team->id,
        ]);
        
        $category->save();
        
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
        
        // Get possible parent categories for dropdown, excluding self and descendants
        $excludeIds = $this->getAllChildrenIds($category);
        $excludeIds[] = $category->id;
        
        $parentCategories = Category::where('team_id', $team->id)
            ->whereNull('parent_id')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
        
        return view('category.form', compact('category', 'modules', 'parentCategories', 'team'));
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
        
        if ($usageCount > 0) {
            return response()->json([
                'error' => "This category is being used by {$usageCount} items and cannot be deleted."
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
        
        foreach ($request->categories as $item) {
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
            'totalAmount' => $totalAmount
        ]);
    }
    
    /**
     * Get the depth of a category in the hierarchy.
     */
    private function getCategoryDepth($category)
    {
        $depth = 0;
        $current = $category;
        
        while ($current->parent_id) {
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
        
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllChildrenIds($child));
        }
        
        return $ids;
    }
    
    /**
     * Count how many items are using this category.
     */
    private function getCategoryUsageCount($category)
    {
        return $category->invoiceItems()->count() + $category->services()->count();
    }
}