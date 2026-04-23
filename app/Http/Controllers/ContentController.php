<?php

namespace App\Http\Controllers;

use App\DataTables\ContentDataTable;
use App\Http\Requests\Content\StoreContentRequest;
use App\Http\Requests\Content\UpdateContentRequest;
use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Models\Multimedia;
use App\Models\Team;
use App\Support\ContentsSectionCategoryData;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

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

        $data['data'] = $this->upsertCoverFromRequest(
            $request,
            $section,
            $data['data'] ?? [],
            null,
        );

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
            ->route('contents.show', $content->id)
            ->with('success', __('app.Content created successfully.'));
    }

    public function show(Content $content)
    {
        $this->authorize('view', $content);

        $content->load(['sectionCategory', 'category', 'multimedia', 'creator', 'updater']);

        return view('contents.show', compact('content'));
    }

    public function edit(Request $request, Content $content)
    {
        $this->authorize('update', $content);

        $team = Auth::user()->currentTeam;

        $contentsModuleId = Module::where('key', 'contents')->value('id');
        $sectionCategories = $this->getFilteredSectionCategories($team->id, $contentsModuleId);

        $selectedSectionId = $request->filled('section_id') ? (int) $request->input('section_id') : null;
        $selectedSection = null;
        if ($selectedSectionId)
        {
            $selectedSection = Category::query()
                ->where('team_id', $team->id)
                ->where('module_id', $contentsModuleId)
                ->where('status', true)
                ->find($selectedSectionId);
        }
        if (! $selectedSection)
        {
            $selectedSection = $content->sectionCategory;
        }

        $fieldConfigs = collect();
        if ($selectedSection)
        {
            $fieldConfigs = $selectedSection->contentFieldConfigs()->active()->ordered()->get();
        }
        $selectedMultimedia = $content->multimedia->pluck('id')->toArray();

        $availableLocales = $this->availableLocalesForContent(null, $selectedSection);
        $contentFormVisibility = $this->contentFormVisibilityForContent(null, $selectedSection);

        return view('contents.form', compact('content', 'sectionCategories', 'fieldConfigs', 'selectedMultimedia', 'team', 'availableLocales', 'contentFormVisibility', 'selectedSection'));
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

        $data['data'] = $this->upsertCoverFromRequest(
            $request,
            $section,
            $data['data'] ?? [],
            $content,
        );

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
            ->route('contents.show', $content->id)
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
            $this->appendCategoryHierarchy($result, $topLevel, 0);
        }

        return $result->values();
    }

    private function appendCategoryHierarchy(\Illuminate\Support\Collection $result, Category $category, int $depth): void
    {
        $category->depth_level = $depth;
        $result->push($category);

        foreach ($category->children as $child)
        {
            $this->appendCategoryHierarchy($result, $child, $depth + 1);
        }
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

    /**
     * @param  array<string, mixed>  $dataFields
     * @return array<string, mixed>
     */
    private function upsertCoverFromRequest(Request $request, Category $section, array $dataFields, ?Content $existingContent): array
    {
        if ($request->boolean('remove_cover_image'))
        {
            $this->deleteCoverFromData($dataFields);
        }

        if (! $request->hasFile('cover_image'))
        {
            return $dataFields;
        }

        if ($existingContent)
        {
            $this->deleteCoverFromData($dataFields);
        }

        $settings = $this->resolveCoverSettings($section);
        $cover = $this->storeCoverImage($request->file('cover_image'), $settings);

        $dataFields['cover'] = $cover;

        return $dataFields;
    }

    /**
     * @return array{max_width: int|null, max_height: int|null, crop: bool, variants: array<string, array{width: int|null, height: int|null, fit: string}>}
     */
    private function resolveCoverSettings(Category $section): array
    {
        $coverData = is_array($section->data['cover'] ?? null) ? $section->data['cover'] : [];
        $rawVariants = is_array($coverData['variants'] ?? null) ? $coverData['variants'] : [];

        $maxWidth = isset($coverData['max_width']) ? (int) $coverData['max_width'] : null;
        $maxHeight = isset($coverData['max_height']) ? (int) $coverData['max_height'] : null;
        $variants = [];
        foreach ($rawVariants as $variantKey => $variantCfg)
        {
            if (! is_string($variantKey) || ! is_array($variantCfg))
            {
                continue;
            }

            $variants[$variantKey] = [
                'width' => isset($variantCfg['width']) ? (int) $variantCfg['width'] : null,
                'height' => isset($variantCfg['height']) ? (int) $variantCfg['height'] : null,
                'fit' => isset($variantCfg['fit']) && is_string($variantCfg['fit']) ? $variantCfg['fit'] : 'max',
            ];
        }

        return [
            'max_width' => $maxWidth ?: null,
            'max_height' => $maxHeight ?: null,
            'crop' => ! empty($coverData['crop']),
            'variants' => $variants,
        ];
    }

    /**
     * @param  array{max_width: int|null, max_height: int|null, crop: bool, variants: array<string, array{width: int|null, height: int|null, fit: string}>}  $settings
     * @return array<string, mixed>
     */
    private function storeCoverImage(UploadedFile $file, array $settings): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeExtension = $extension !== '' ? $extension : 'jpg';
        $filename = Str::uuid()->toString().'.'.$safeExtension;
        $teamId = Auth::user()?->currentTeam?->id;
        $teamHash = $teamId ? Team::generateTeamHash($teamId) : 'default';
        $basePath = "contents/{$teamHash}/covers";
        $relativePath = $basePath.'/'.$filename;
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! is_dir(dirname($absolutePath)))
        {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $maxWidth = $settings['max_width'];
        $maxHeight = $settings['max_height'];

        if ($maxWidth || $maxHeight)
        {
            $image = Image::load($file->getRealPath());

            if ($maxWidth && $maxHeight)
            {
                $fit = $settings['crop'] ? Fit::Crop : Fit::Max;
                $image->fit($fit, $maxWidth, $maxHeight);
            } elseif ($maxWidth)
            {
                $image->width($maxWidth);
            } elseif ($maxHeight)
            {
                $image->height($maxHeight);
            }

            $image->save($absolutePath);
        } else
        {
            Storage::disk('public')->putFileAs($basePath, $file, $filename);
        }

        [$storedWidth, $storedHeight] = $this->extractImageDimensions($absolutePath);

        $variantData = $this->storeCoverVariants($file, $settings, $basePath);

        return [
            'url' => asset('storage/'.$relativePath),
            'path' => $relativePath,
            'width' => $storedWidth,
            'height' => $storedHeight,
            'mime_type' => $file->getMimeType(),
            'size' => Storage::disk('public')->size($relativePath),
            'max_width' => $maxWidth,
            'max_height' => $maxHeight,
            'crop' => $settings['crop'],
            'variants' => $variantData,
        ];
    }

    /**
     * @param  array{max_width: int|null, max_height: int|null, crop: bool, variants: array<string, array{width: int|null, height: int|null, fit: string}>}  $settings
     * @return array<string, array<string, mixed>>
     */
    private function storeCoverVariants(UploadedFile $file, array $settings, string $basePath): array
    {
        $out = [];
        $variants = $settings['variants'] ?? [];
        foreach ($variants as $variantKey => $variantCfg)
        {
            $width = $variantCfg['width'] ?? null;
            $height = $variantCfg['height'] ?? null;
            if (! $width && ! $height)
            {
                continue;
            }

            $fit = is_string($variantCfg['fit'] ?? null) ? $variantCfg['fit'] : 'max';
            $safeVariantKey = Str::slug((string) $variantKey, '_');
            if ($safeVariantKey === '')
            {
                continue;
            }

            $filename = $safeVariantKey.'_'.Str::uuid().'.webp';
            $relativePath = $basePath.'/variants/'.$filename;
            $absolutePath = Storage::disk('public')->path($relativePath);
            if (! is_dir(dirname($absolutePath)))
            {
                mkdir(dirname($absolutePath), 0775, true);
            }

            $image = Image::load($file->getRealPath());
            $resolvedFit = match ($fit)
            {
                'crop' => Fit::Crop,
                'contain' => Fit::Contain,
                'stretch' => Fit::Stretch,
                default => Fit::Max,
            };

            if ($width && $height)
            {
                $image->fit($resolvedFit, (int) $width, (int) $height);
            } elseif ($width)
            {
                $image->width((int) $width);
            } else
            {
                $image->height((int) $height);
            }

            $image->save($absolutePath);
            [$storedWidth, $storedHeight] = $this->extractImageDimensions($absolutePath);

            $out[$safeVariantKey] = [
                'url' => asset('storage/'.$relativePath),
                'path' => $relativePath,
                'width' => $storedWidth,
                'height' => $storedHeight,
                'fit' => $fit,
                'size' => Storage::disk('public')->size($relativePath),
                'mime_type' => 'image/webp',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $dataFields
     */
    private function deleteCoverFromData(array &$dataFields): void
    {
        $coverPath = is_array($dataFields['cover'] ?? null) ? ($dataFields['cover']['path'] ?? null) : null;
        if (is_string($coverPath) && $coverPath !== '' && Storage::disk('public')->exists($coverPath))
        {
            Storage::disk('public')->delete($coverPath);
        }

        $variants = is_array($dataFields['cover']['variants'] ?? null) ? $dataFields['cover']['variants'] : [];
        foreach ($variants as $variant)
        {
            $variantPath = is_array($variant) ? ($variant['path'] ?? null) : null;
            if (is_string($variantPath) && $variantPath !== '' && Storage::disk('public')->exists($variantPath))
            {
                Storage::disk('public')->delete($variantPath);
            }
        }

        unset($dataFields['cover']);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function extractImageDimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);
        if (! is_array($size))
        {
            return [null, null];
        }

        return [
            isset($size[0]) ? (int) $size[0] : null,
            isset($size[1]) ? (int) $size[1] : null,
        ];
    }
}
