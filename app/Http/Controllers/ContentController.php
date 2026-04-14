<?php

namespace App\Http\Controllers;

use App\DataTables\ContentDataTable;
use App\Http\Requests\Content\StoreContentRequest;
use App\Http\Requests\Content\UpdateContentRequest;
use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\Multimedia;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentController extends Controller
{
    public function index(ContentDataTable $dataTable, Request $request)
    {
        $this->authorize('viewAny', Content::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');
        $categoryId = $request->get('category_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = $this->getFilteredSectionCategories($team->id, $contentsModuleId);

        if ($request->ajax())
        {
            return $dataTable->ajax();
        }

        return $dataTable->render('contents.index', compact('dataTable', 'sectionCategories', 'sectionId', 'team'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Content::class);

        $team = Auth::user()->currentTeam;
        $sectionId = $request->get('section_id');

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = $this->getFilteredSectionCategories($team->id, $contentsModuleId);

        $selectedSection = $sectionId ? Category::find($sectionId) : null;
        $fieldConfigs = $selectedSection ? $selectedSection->contentFieldConfigs()->active()->ordered()->get() : collect();

        $availableLocales = $this->availableLocalesForContent(null, $selectedSection);
        $contentFormVisibility = $this->contentFormVisibilityForContent(null, $selectedSection);

        return view('contents.form', compact('sectionCategories', 'selectedSection', 'fieldConfigs', 'team', 'availableLocales', 'contentFormVisibility'));
    }

    public function store(StoreContentRequest $request)
    {
        $this->authorize('create', Content::class);

        $team = Auth::user()->currentTeam;
        $data = $request->validated();

        // Handle boolean checkboxes - set to false if not present
        $data['featured'] = $request->has('featured') && $request->input('featured') == '1';
        $data['featured_slide'] = $request->has('featured_slide') && $request->input('featured_slide') == '1';
        $data['featured_modal'] = $request->has('featured_modal') && $request->input('featured_modal') == '1';

        $section = Category::findOrFail($data['section_category_id']);

        if ($section->contentsPageSectionHistoryTimeline() && empty($data['template'] ?? null))
        {
            $data['template'] = 'timeline_item';
        }

        $localeCodes = $section->contentFormLocales();

        // Prepare translatable fields for all locales
        $translatableFields = ['title', 'subtitle', 'url', 'content', 'seo_title', 'seo_keywords', 'seo_description'];

        foreach ($translatableFields as $field)
        {
            $fieldData = [];
            foreach ($localeCodes as $locale)
            {
                $fieldKey = "{$field}_{$locale}";
                if ($request->has($fieldKey) && $request->input($fieldKey) !== null && $request->input($fieldKey) !== '')
                {
                    $fieldData[$locale] = $request->input($fieldKey);
                }
            }
            if (! empty($fieldData))
            {
                $data[$field] = $fieldData;
            }
        }

        // Extract data fields (additional fields from config)
        $dataFields = [];
        $fieldConfigs = $section->contentFieldConfigs()->active()->get();

        foreach ($fieldConfigs as $config)
        {
            $key = $config->field_key;
            if ($request->has("data.{$key}"))
            {
                $dataFields[$key] = $request->input("data.{$key}");
            }
        }

        if (! empty($dataFields))
        {
            $data['data'] = $dataFields;
        }

        $data['team_id'] = $team->id;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $content = Content::create($data);

        // Sync multimedia
        if ($request->has('multimedia'))
        {
            $this->syncMultimedia($content, $request->input('multimedia', []));
        }

        return redirect()
            ->route('contents.index', ['section_id' => $content->section_category_id])
            ->with('success', __('app.Content created successfully.'));
    }

    public function show(Content $content)
    {
        $this->authorize('view', $content);

        $content->load(['sectionCategory', 'category', 'multimedia', 'creator', 'updater']);

        return view('contents.show', compact('content'));
    }

    public function edit(Content $content)
    {
        $this->authorize('update', $content);

        $team = Auth::user()->currentTeam;

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = $this->getFilteredSectionCategories($team->id, $contentsModuleId);

        $fieldConfigs = collect();
        if ($content->sectionCategory)
        {
            $fieldConfigs = $content->sectionCategory->contentFieldConfigs()->active()->ordered()->get();
        }
        $selectedMultimedia = $content->multimedia->pluck('id')->toArray();

        $availableLocales = $this->availableLocalesForContent($content, null);
        $contentFormVisibility = $this->contentFormVisibilityForContent($content, null);

        return view('contents.form', compact('content', 'sectionCategories', 'fieldConfigs', 'selectedMultimedia', 'team', 'availableLocales', 'contentFormVisibility'));
    }

    public function update(UpdateContentRequest $request, Content $content)
    {
        $this->authorize('update', $content);

        $data = $request->validated();

        // Handle boolean checkboxes - set to false if not present
        $data['featured'] = $request->has('featured') && $request->input('featured') == '1';
        $data['featured_slide'] = $request->has('featured_slide') && $request->input('featured_slide') == '1';
        $data['featured_modal'] = $request->has('featured_modal') && $request->input('featured_modal') == '1';

        $sectionId = $data['section_category_id'] ?? $content->section_category_id;
        $section = Category::findOrFail($sectionId);

        if ($section->contentsPageSectionHistoryTimeline() && empty($data['template'] ?? null))
        {
            $data['template'] = 'timeline_item';
        }

        $localeCodes = $section->contentFormLocales();

        // Prepare translatable fields for all locales
        $translatableFields = ['title', 'subtitle', 'url', 'content', 'seo_title', 'seo_keywords', 'seo_description'];

        foreach ($translatableFields as $field)
        {
            $current = $content->$field ?? [];
            if (! is_array($current))
            {
                $current = [];
            }

            foreach ($localeCodes as $locale)
            {
                $fieldKey = "{$field}_{$locale}";
                if ($request->has($fieldKey) && $request->input($fieldKey) !== null && $request->input($fieldKey) !== '')
                {
                    $current[$locale] = $request->input($fieldKey);
                }
            }

            if (! empty($current))
            {
                $data[$field] = $current;
            }
        }

        // Extract data fields (additional fields from config)
        $dataFields = $content->data ?? [];
        $fieldConfigs = $section->contentFieldConfigs()->active()->get();

        foreach ($fieldConfigs as $config)
        {
            $key = $config->field_key;
            if ($request->has("data.{$key}"))
            {
                $dataFields[$key] = $request->input("data.{$key}");
            }
        }

        if (! empty($dataFields))
        {
            $data['data'] = $dataFields;
        }

        $data['updated_by'] = Auth::id();

        $content->update($data);

        // Sync multimedia
        if ($request->has('multimedia') && is_array($request->input('multimedia')))
        {
            $multimediaIds = array_filter($request->input('multimedia', []));
            if (! empty($multimediaIds))
            {
                $this->syncMultimedia($content, $multimediaIds);
            } else
            {
                $content->multimedia()->detach();
            }
        }

        return redirect()
            ->route('contents.index', ['section_id' => $content->section_category_id])
            ->with('success', __('app.Content updated successfully.'));
    }

    public function destroy(Content $content)
    {
        $this->authorize('delete', $content);

        $content->delete();

        return response()->json([
            'success' => __('app.Content deleted successfully.'),
        ]);
    }

    public function updateOrder(Request $request)
    {
        $this->authorize('update', Content::class);

        $request->validate([
            'contents' => 'required|array',
            'contents.*.id' => 'required|exists:contents,id',
            'contents.*.order' => 'required|integer|min:0',
        ]);

        $team = Auth::user()->currentTeam;

        foreach ($request->contents as $item)
        {
            Content::where('team_id', $team->id)
                ->where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json(['success' => __('app.Order updated successfully.')], 200);
    }

    /**
     * Locale labels for content/SEO tabs from the section category (or all supported when no section).
     *
     * @return array<string, string>
     */
    private function availableLocalesForContent(?Content $content, ?Category $selectedSection): array
    {
        $section = $content?->sectionCategory;
        if (! $section && $selectedSection)
        {
            $section = $selectedSection;
        }
        if (! $section && request()->old('section_category_id'))
        {
            $section = Category::find(request()->old('section_category_id'));
        }

        $labels = ContentsSectionCategoryData::supportedLocaleLabels();
        if (! $section)
        {
            return $labels;
        }

        $codes = $section->contentFormLocales();
        $map = [];
        foreach ($codes as $code)
        {
            if (isset($labels[$code]))
            {
                $map[$code] = $labels[$code];
            }
        }

        return $map !== [] ? $map : ['es' => $labels['es']];
    }

    /**
     * Resolved visibility for standard fields on the contents form from the section category.
     *
     * @return array{
     *     show_title: bool,
     *     show_subtitle: bool,
     *     show_url: bool,
     *     show_main_content: bool,
     *     show_featured: bool,
     *     show_seo: bool,
     *     show_multimedia: bool
     * }
     */
    private function contentFormVisibilityForContent(?Content $content, ?Category $selectedSection): array
    {
        $section = $content?->sectionCategory;
        if (! $section && $selectedSection)
        {
            $section = $selectedSection;
        }
        if (! $section && request()->old('section_category_id'))
        {
            $section = Category::find(request()->old('section_category_id'));
        }

        return $section
            ? $section->contentFormVisibility()
            : ContentsSectionCategoryData::defaultContentFormVisibility();
    }

    /**
     * Get filtered section categories for content selectors.
     * Top level categories only appear if they have less than 2 active subcategories.
     * If they have 2+ subcategories, show the subcategories directly instead of the top level.
     */
    private function getFilteredSectionCategories(int $teamId, int $moduleId): \Illuminate\Support\Collection
    {
        // Get all top level categories for this module
        $topLevelCategories = Category::where('team_id', $teamId)
            ->where('module_id', $moduleId)
            ->whereNull('parent_id')
            ->where('status', true)
            ->with(['children' => function ($query)
            {
                $query->where('status', true)
                    ->orderBy('order')
                    ->orderBy('name');
            }])
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $result = collect();

        foreach ($topLevelCategories as $topLevel)
        {
            // Children are already filtered by status and ordered in eager loading
            $activeChildren = $topLevel->children;

            if ($activeChildren->count() >= 2)
            {
                // Top level has 2+ subcategories → show subcategories directly (not the top level)
                // Add parent order as a temporary attribute to maintain parent ordering
                foreach ($activeChildren as $child)
                {
                    $child->parent_order = $topLevel->order;
                }
                $result = $result->merge($activeChildren);
            } else
            {
                // Top level has < 2 subcategories → include the top level itself
                // Set parent_order to its own order for consistent sorting
                $topLevel->parent_order = $topLevel->order;
                $result->push($topLevel);
                if ($activeChildren->count() === 1)
                {
                    $activeChildren->first()->parent_order = $topLevel->order;
                    $result = $result->merge($activeChildren);
                }
            }
        }

        // Sort the final collection: first by parent order, then by category order, then by name
        // This maintains the relative order within each parent group
        return $result->sortBy(function ($item)
        {
            return [
                $item->parent_order ?? $item->order ?? 999,
                $item->order ?? 999,
                $item->name ?? '',
            ];
        })->values();
    }

    private function syncMultimedia(Content $content, array $multimediaData): void
    {
        $content->multimedia()->detach();

        if (empty($multimediaData))
        {
            return;
        }

        // Handle array of IDs or array of objects
        foreach ($multimediaData as $index => $item)
        {
            $multimediaId = is_array($item) ? ($item['id'] ?? null) : $item;

            if ($multimediaId)
            {
                $content->multimedia()->attach($multimediaId, [
                    'language' => (is_array($item) && isset($item['language'])) ? $item['language'] : app()->getLocale(),
                    'type' => (is_array($item) && isset($item['type'])) ? $item['type'] : 1,
                    'order' => $index,
                ]);
            }
        }
    }
}
