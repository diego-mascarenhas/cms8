<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Content;
use App\Models\Module;
use App\Support\TeamContentsApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class TeamContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $team = $request->attributes->get('team');

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 401);
        }

        $ttlSeconds = $this->teamContentsIndexCacheTtlSeconds();
        if ($ttlSeconds === 0)
        {
            return $this->executeIndex($request, $team);
        }

        $teamId = (int) $team->id;
        $generation = TeamContentsApiCache::currentGeneration($teamId);
        $cacheKey = TeamContentsApiCache::indexCacheKey($teamId, $generation, $request);

        $resolver = function () use ($request, $team)
        {
            $response = $this->executeIndex($request, $team);

            return [
                'status' => $response->getStatusCode(),
                'payload' => $response->getData(true),
            ];
        };

        if ($ttlSeconds < 0)
        {
            $cached = Cache::rememberForever($cacheKey, $resolver);
        } else
        {
            $cached = Cache::remember($cacheKey, $ttlSeconds, $resolver);
        }

        return response()->json($cached['payload'], $cached['status']);
    }

    /**
     * Uncached JSON response for GET /api/team/contents (index).
     */
    private function executeIndex(Request $request, $team): JsonResponse
    {
        $query = Content::where('team_id', $team->id);
        $resolvedSectionCategory = null;
        $contentsModuleId = Module::where('key', 'contents')->value('id');

        // Filter by section slug configured in section category data.slug
        if ($request->filled('section_slug'))
        {
            $sectionSlug = trim((string) $request->input('section_slug'));
            $resolvedSectionCategory = Category::query()
                ->where('team_id', $team->id)
                ->where('module_id', $contentsModuleId)
                ->where('status', 1)
                ->where('data->slug', $sectionSlug)
                ->first();

            if (! $resolvedSectionCategory)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Section slug not found',
                ], 404);
            }

            $query->where('section_category_id', $resolvedSectionCategory->id);
        }

        // Filter by section category
        if ($request->has('section_category_id'))
        {
            $sectionCategoryId = (int) $request->input('section_category_id');
            $query->where('section_category_id', $sectionCategoryId);
            if (! $resolvedSectionCategory)
            {
                $resolvedSectionCategory = Category::query()
                    ->where('team_id', $team->id)
                    ->where('module_id', $contentsModuleId)
                    ->where('status', 1)
                    ->where('id', $sectionCategoryId)
                    ->first();
            }
        }

        // Filter by category
        if ($request->has('category_id'))
        {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by status
        if ($request->has('status'))
        {
            $query->where('status', $request->input('status'));
        }

        // Filter by featured
        if ($request->has('featured'))
        {
            $query->where('featured', $request->boolean('featured'));
        }

        // Search
        if ($request->has('search'))
        {
            $search = $request->input('search');
            $query->where(function ($q) use ($search)
            {
                $q->whereRaw("JSON_EXTRACT(title, '$.es') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_EXTRACT(title, '$.en') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_EXTRACT(subtitle, '$.es') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_EXTRACT(subtitle, '$.en') LIKE ?", ["%{$search}%"]);
            });
        }

        // Get locale for translatable fields
        $locale = $request->input('locale', 'es');
        self::applyOrderingRules($query, $resolvedSectionCategory, $locale);

        $perPage = $request->input('per_page', 20);
        $contents = $query->with(['sectionCategory:id,name,data', 'category:id,name'])
            ->paginate($perPage);

        // Transform contents to include translatable fields
        $contents->getCollection()->transform(function ($content) use ($locale)
        {
            return [
                'id' => $content->id,
                'title' => $content->getTranslatable('title', $locale),
                'subtitle' => $content->getTranslatable('subtitle', $locale),
                'url' => $content->getTranslatable('url', $locale),
                'content' => $content->getTranslatable('content', $locale),
                'section_category' => self::transformSectionCategory($content->sectionCategory),
                'category' => $content->category ? [
                    'id' => $content->category->id,
                    'name' => $content->category->name,
                ] : null,
                'status' => $content->status,
                'featured' => $content->featured,
                'featured_slide' => $content->featured_slide,
                'featured_modal' => $content->featured_modal,
                'order' => $content->order,
                'template' => $content->template,
                'seo_title' => $content->getTranslatable('seo_title', $locale),
                'seo_keywords' => $content->getTranslatable('seo_keywords', $locale),
                'seo_description' => $content->getTranslatable('seo_description', $locale),
                'data' => $content->data,
                'cover' => self::transformCover($content->data['cover'] ?? null),
                'created_at' => $content->created_at,
                'updated_at' => $content->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $contents,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $team = $request->attributes->get('team');

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 401);
        }

        $contentsModuleId = Module::where('key', 'contents')->value('id');

        $validated = $request->validate([
            'section_category_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('categories', 'id')->where(function ($query) use ($contentsModuleId)
                {
                    $query->where('module_id', $contentsModuleId);
                }),
            ],
            'category_id' => 'nullable|exists:categories,id',
            'template' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0|max:255',
            'status' => 'required|integer|in:1,2,3,4',
            'featured' => 'sometimes|boolean',
            'featured_slide' => 'sometimes|boolean',
            'featured_modal' => 'sometimes|boolean',
            // Multi-language fields
            'title_es' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'title_it' => 'nullable|string|max:255',
            'title_pt' => 'nullable|string|max:255',
            'title_fr' => 'nullable|string|max:255',
            'title_de' => 'nullable|string|max:255',
            'subtitle_es' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:255',
            'subtitle_it' => 'nullable|string|max:255',
            'subtitle_pt' => 'nullable|string|max:255',
            'subtitle_fr' => 'nullable|string|max:255',
            'subtitle_de' => 'nullable|string|max:255',
            'url_es' => 'nullable|string|max:255',
            'url_en' => 'nullable|string|max:255',
            'url_it' => 'nullable|string|max:255',
            'url_pt' => 'nullable|string|max:255',
            'url_fr' => 'nullable|string|max:255',
            'url_de' => 'nullable|string|max:255',
            'content_es' => 'nullable|string',
            'content_en' => 'nullable|string',
            'content_it' => 'nullable|string',
            'content_pt' => 'nullable|string',
            'content_fr' => 'nullable|string',
            'content_de' => 'nullable|string',
            'seo_title_es' => 'nullable|string|max:255',
            'seo_title_en' => 'nullable|string|max:255',
            'seo_title_it' => 'nullable|string|max:255',
            'seo_title_pt' => 'nullable|string|max:255',
            'seo_title_fr' => 'nullable|string|max:255',
            'seo_title_de' => 'nullable|string|max:255',
            'seo_keywords_es' => 'nullable|string|max:255',
            'seo_keywords_en' => 'nullable|string|max:255',
            'seo_keywords_it' => 'nullable|string|max:255',
            'seo_keywords_pt' => 'nullable|string|max:255',
            'seo_keywords_fr' => 'nullable|string|max:255',
            'seo_keywords_de' => 'nullable|string|max:255',
            'seo_description_es' => 'nullable|string',
            'seo_description_en' => 'nullable|string',
            'seo_description_it' => 'nullable|string',
            'seo_description_pt' => 'nullable|string',
            'seo_description_fr' => 'nullable|string',
            'seo_description_de' => 'nullable|string',
            'multimedia' => 'nullable|array',
            'multimedia.*' => 'exists:multimedia,id',
            'data' => 'nullable|array',
        ]);

        try
        {
            $data = $validated;

            // Handle boolean checkboxes
            $data['featured'] = $request->has('featured') && $request->input('featured') == '1';
            $data['featured_slide'] = $request->has('featured_slide') && $request->input('featured_slide') == '1';
            $data['featured_modal'] = $request->has('featured_modal') && $request->input('featured_modal') == '1';

            // Prepare translatable fields for all locales
            $translatableFields = ['title', 'subtitle', 'url', 'content', 'seo_title', 'seo_keywords', 'seo_description'];
            $availableLocales = ['es', 'en', 'it', 'pt', 'fr', 'de'];

            foreach ($translatableFields as $field)
            {
                $fieldData = [];
                foreach ($availableLocales as $locale)
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
            $section = \App\Models\Category::findOrFail($data['section_category_id']);
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
            $data['created_by'] = 1; // System user for team tokens
            $data['updated_by'] = 1;

            $content = Content::create($data);

            // Sync multimedia
            if ($request->has('multimedia') && is_array($request->input('multimedia')))
            {
                $content->multimedia()->sync($request->input('multimedia'));
            }

            // Get locale for response
            $locale = $request->input('locale', 'es');

            return response()->json([
                'success' => true,
                'message' => 'Content created successfully',
                'data' => [
                    'id' => $content->id,
                    'title' => $content->getTranslatable('title', $locale),
                    'subtitle' => $content->getTranslatable('subtitle', $locale),
                    'section_category' => self::transformSectionCategory($content->sectionCategory),
                ],
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ], 201);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error creating content: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 401);
        }

        $content = Content::where('team_id', $team->id)
            ->where('id', $id)
            ->with(['sectionCategory:id,name,data', 'category:id,name', 'multimedia'])
            ->firstOrFail();

        // Get locale for translatable fields
        $locale = $request->input('locale', 'es');

        $transformedContent = [
            'id' => $content->id,
            'title' => $content->getTranslatable('title', $locale),
            'subtitle' => $content->getTranslatable('subtitle', $locale),
            'url' => $content->getTranslatable('url', $locale),
            'content' => $content->getTranslatable('content', $locale),
            'section_category' => self::transformSectionCategory($content->sectionCategory),
            'category' => $content->category ? [
                'id' => $content->category->id,
                'name' => $content->category->name,
            ] : null,
            'status' => $content->status,
            'featured' => $content->featured,
            'featured_slide' => $content->featured_slide,
            'featured_modal' => $content->featured_modal,
            'order' => $content->order,
            'template' => $content->template,
            'seo_title' => $content->getTranslatable('seo_title', $locale),
            'seo_keywords' => $content->getTranslatable('seo_keywords', $locale),
            'seo_description' => $content->getTranslatable('seo_description', $locale),
            'data' => $content->data,
            'cover' => self::transformCover($content->data['cover'] ?? null),
            'multimedia' => $content->multimedia->map(function ($media)
            {
                return [
                    'id' => $media->id,
                    'title' => $media->title,
                    'type' => $media->type,
                ];
            }),
            'created_at' => $content->created_at,
            'updated_at' => $content->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $transformedContent,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 401);
        }

        $content = Content::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        $contentsModuleId = Module::where('key', 'contents')->value('id');

        $validated = $request->validate([
            'section_category_id' => [
                'sometimes',
                'required',
                \Illuminate\Validation\Rule::exists('categories', 'id')->where(function ($query) use ($contentsModuleId)
                {
                    $query->where('module_id', $contentsModuleId);
                }),
            ],
            'category_id' => 'nullable|exists:categories,id',
            'template' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0|max:255',
            'status' => 'sometimes|required|integer|in:1,2,3,4',
            'featured' => 'sometimes|boolean',
            'featured_slide' => 'sometimes|boolean',
            'featured_modal' => 'sometimes|boolean',
            // Multi-language fields
            'title_es' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'title_it' => 'nullable|string|max:255',
            'title_pt' => 'nullable|string|max:255',
            'title_fr' => 'nullable|string|max:255',
            'title_de' => 'nullable|string|max:255',
            'subtitle_es' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:255',
            'subtitle_it' => 'nullable|string|max:255',
            'subtitle_pt' => 'nullable|string|max:255',
            'subtitle_fr' => 'nullable|string|max:255',
            'subtitle_de' => 'nullable|string|max:255',
            'url_es' => 'nullable|string|max:255',
            'url_en' => 'nullable|string|max:255',
            'url_it' => 'nullable|string|max:255',
            'url_pt' => 'nullable|string|max:255',
            'url_fr' => 'nullable|string|max:255',
            'url_de' => 'nullable|string|max:255',
            'content_es' => 'nullable|string',
            'content_en' => 'nullable|string',
            'content_it' => 'nullable|string',
            'content_pt' => 'nullable|string',
            'content_fr' => 'nullable|string',
            'content_de' => 'nullable|string',
            'seo_title_es' => 'nullable|string|max:255',
            'seo_title_en' => 'nullable|string|max:255',
            'seo_title_it' => 'nullable|string|max:255',
            'seo_title_pt' => 'nullable|string|max:255',
            'seo_title_fr' => 'nullable|string|max:255',
            'seo_title_de' => 'nullable|string|max:255',
            'seo_keywords_es' => 'nullable|string|max:255',
            'seo_keywords_en' => 'nullable|string|max:255',
            'seo_keywords_it' => 'nullable|string|max:255',
            'seo_keywords_pt' => 'nullable|string|max:255',
            'seo_keywords_fr' => 'nullable|string|max:255',
            'seo_keywords_de' => 'nullable|string|max:255',
            'seo_description_es' => 'nullable|string',
            'seo_description_en' => 'nullable|string',
            'seo_description_it' => 'nullable|string',
            'seo_description_pt' => 'nullable|string',
            'seo_description_fr' => 'nullable|string',
            'seo_description_de' => 'nullable|string',
            'multimedia' => 'nullable|array',
            'multimedia.*' => 'exists:multimedia,id',
            'data' => 'nullable|array',
        ]);

        try
        {
            $data = $validated;

            // Handle boolean checkboxes
            if ($request->has('featured'))
            {
                $data['featured'] = $request->boolean('featured');
            }
            if ($request->has('featured_slide'))
            {
                $data['featured_slide'] = $request->boolean('featured_slide');
            }
            if ($request->has('featured_modal'))
            {
                $data['featured_modal'] = $request->boolean('featured_modal');
            }

            // Prepare translatable fields for all locales
            $translatableFields = ['title', 'subtitle', 'url', 'content', 'seo_title', 'seo_keywords', 'seo_description'];
            $availableLocales = ['es', 'en', 'it', 'pt', 'fr', 'de'];

            foreach ($translatableFields as $field)
            {
                $current = $content->$field ?? [];
                if (! is_array($current))
                {
                    $current = [];
                }

                foreach ($availableLocales as $locale)
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
            $sectionId = $data['section_category_id'] ?? $content->section_category_id;
            $section = \App\Models\Category::findOrFail($sectionId);
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

            $data['updated_by'] = 1; // System user for team tokens

            $content->update($data);

            // Sync multimedia
            if ($request->has('multimedia') && is_array($request->input('multimedia')))
            {
                $content->multimedia()->sync($request->input('multimedia'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Content updated successfully',
                'data' => $content->load(['sectionCategory:id,name,data', 'category:id,name']),
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error updating content: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $team = $request->attributes->get('team');

        if (! $team)
        {
            return response()->json([
                'success' => false,
                'message' => 'Team not found',
            ], 401);
        }

        $content = Content::where('team_id', $team->id)
            ->where('id', $id)
            ->firstOrFail();

        try
        {
            $content->delete();

            return response()->json([
                'success' => true,
                'message' => 'Content deleted successfully',
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting content: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{id: int, name: string, data: array|null}|null
     */
    private static function transformSectionCategory(?Category $sectionCategory): ?array
    {
        if (! $sectionCategory)
        {
            return null;
        }

        return [
            'id' => $sectionCategory->id,
            'name' => $sectionCategory->name,
            'data' => $sectionCategory->data,
        ];
    }

    private static function applyOrderingRules($query, ?Category $sectionCategory, string $locale): void
    {
        if (! $sectionCategory)
        {
            $query->orderBy('order')->orderBy('created_at', 'desc');

            return;
        }

        $ordering = $sectionCategory->getContentOrdering();
        if (! is_array($ordering) || $ordering === [])
        {
            $query->orderBy('order')->orderBy('created_at', 'desc');

            return;
        }

        $applied = false;
        foreach ($ordering as $rule)
        {
            if (! is_array($rule))
            {
                continue;
            }

            $column = $rule['column'] ?? null;
            $direction = strtolower((string) ($rule['direction'] ?? 'asc'));
            if (! in_array($direction, ['asc', 'desc'], true))
            {
                $direction = 'asc';
            }

            if ($column === 'title')
            {
                $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"')) {$direction}");
                $applied = true;

                continue;
            }

            if (in_array($column, ['order', 'created_at', 'updated_at'], true))
            {
                $query->orderBy($column, $direction);
                $applied = true;
            }
        }

        if (! $applied)
        {
            $query->orderBy('order')->orderBy('created_at', 'desc');
        }
    }

    /**
     * @return int 0 = cache off; negative = forever until generation bump; positive = TTL seconds
     */
    private function teamContentsIndexCacheTtlSeconds(): int
    {
        $raw = config('cache.team_contents_index_ttl', '-1');
        if ($raw === null || $raw === '')
        {
            return -1;
        }

        return (int) $raw;
    }

    /**
     * @param  mixed  $cover
     * @return array<string, mixed>|null
     */
    private static function transformCover($cover): ?array
    {
        if (! is_array($cover))
        {
            return null;
        }

        return [
            'url' => self::resolveAbsoluteCoverUrl($cover),
            'width' => $cover['width'] ?? null,
            'height' => $cover['height'] ?? null,
            'mime_type' => $cover['mime_type'] ?? null,
            'size' => $cover['size'] ?? null,
            'max_width' => $cover['max_width'] ?? null,
            'max_height' => $cover['max_height'] ?? null,
            'crop' => $cover['crop'] ?? false,
            'variants' => is_array($cover['variants'] ?? null) ? $cover['variants'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $cover
     */
    private static function resolveAbsoluteCoverUrl(array $cover): ?string
    {
        $url = isset($cover['url']) && is_string($cover['url']) ? trim($cover['url']) : '';
        if ($url !== '')
        {
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))
            {
                return $url;
            }

            if (str_starts_with($url, '/'))
            {
                return URL::to($url);
            }

            return URL::to('/'.ltrim($url, '/'));
        }

        $path = isset($cover['path']) && is_string($cover['path']) ? trim($cover['path']) : '';
        if ($path === '')
        {
            return null;
        }

        return URL::to('/storage/'.ltrim($path, '/'));
    }
}
