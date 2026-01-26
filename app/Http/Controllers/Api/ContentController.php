<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreContentRequest;
use App\Http\Requests\Content\UpdateContentRequest;
use App\Models\Content;
use App\Models\Module;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Check if user can view any contents
        if (! $user->can('viewAny', Content::class))
        {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view contents',
                'data' => [],
            ], 403);
        }

        $query = Content::query();

        // Filter by section category
        if ($request->has('section_category_id'))
        {
            $query->where('section_category_id', $request->input('section_category_id'));
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
        $locale = $request->input('locale', app()->getLocale());

        $contents = $query->with(['sectionCategory:id,name', 'category:id,name'])
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        // Transform contents to include translatable fields
        $transformedContents = $contents->getCollection()->map(function ($content) use ($locale)
        {
            return [
                'id' => $content->id,
                'title' => $content->getTranslatable('title', $locale),
                'subtitle' => $content->getTranslatable('subtitle', $locale),
                'url' => $content->getTranslatable('url', $locale),
                'content' => $content->getTranslatable('content', $locale),
                'section_category' => $content->sectionCategory ? [
                    'id' => $content->sectionCategory->id,
                    'name' => $content->sectionCategory->name,
                ] : null,
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
                'created_at' => $content->created_at,
                'updated_at' => $content->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Contents retrieved successfully',
            'data' => $transformedContents,
            'pagination' => [
                'current_page' => $contents->currentPage(),
                'per_page' => $contents->perPage(),
                'total' => $contents->total(),
                'last_page' => $contents->lastPage(),
            ],
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContentRequest $request)
    {
        $user = auth()->user();

        if (! $user->can('create', Content::class))
        {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create contents',
            ], 403);
        }

        try
        {
            $team = $user->currentTeam;
            $data = $request->validated();

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
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            $content = Content::create($data);

            // Sync multimedia
            if ($request->has('multimedia') && is_array($request->input('multimedia')))
            {
                $content->multimedia()->sync($request->input('multimedia'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Content created successfully',
                'data' => $content->load(['sectionCategory:id,name', 'category:id,name']),
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
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
        $user = auth()->user();
        $content = Content::with(['sectionCategory:id,name', 'category:id,name', 'multimedia'])->find($id);

        if (! $content)
        {
            return response()->json([
                'success' => false,
                'message' => 'Content not found',
            ], 404);
        }

        if (! $user->can('view', $content))
        {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this content',
            ], 403);
        }

        // Get locale for translatable fields
        $locale = $request->input('locale', app()->getLocale());

        $transformedContent = [
            'id' => $content->id,
            'title' => $content->getTranslatable('title', $locale),
            'subtitle' => $content->getTranslatable('subtitle', $locale),
            'url' => $content->getTranslatable('url', $locale),
            'content' => $content->getTranslatable('content', $locale),
            'section_category' => $content->sectionCategory ? [
                'id' => $content->sectionCategory->id,
                'name' => $content->sectionCategory->name,
            ] : null,
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
            'message' => 'Content retrieved successfully',
            'data' => $transformedContent,
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateContentRequest $request, string $id)
    {
        $user = auth()->user();
        $content = Content::find($id);

        if (! $content)
        {
            return response()->json([
                'success' => false,
                'message' => 'Content not found',
            ], 404);
        }

        if (! $user->can('update', $content))
        {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this content',
            ], 403);
        }

        try
        {
            $data = $request->validated();

            // Handle boolean checkboxes
            $data['featured'] = $request->has('featured') && $request->input('featured') == '1';
            $data['featured_slide'] = $request->has('featured_slide') && $request->input('featured_slide') == '1';
            $data['featured_modal'] = $request->has('featured_modal') && $request->input('featured_modal') == '1';

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

            $data['updated_by'] = $user->id;

            $content->update($data);

            // Sync multimedia
            if ($request->has('multimedia') && is_array($request->input('multimedia')))
            {
                $content->multimedia()->sync($request->input('multimedia'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Content updated successfully',
                'data' => $content->load(['sectionCategory:id,name', 'category:id,name']),
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
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
    public function destroy(string $id)
    {
        $user = auth()->user();
        $content = Content::find($id);

        if (! $content)
        {
            return response()->json([
                'success' => false,
                'message' => 'Content not found',
            ], 404);
        }

        if (! $user->can('delete', $content))
        {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this content',
            ], 403);
        }

        try
        {
            $content->delete();

            return response()->json([
                'success' => true,
                'message' => 'Content deleted successfully',
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
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
}
