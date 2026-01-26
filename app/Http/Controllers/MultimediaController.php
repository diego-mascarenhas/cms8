<?php

namespace App\Http\Controllers;

use App\DataTables\MultimediaDataTable;
use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use App\Http\Requests\Multimedia\StoreMultimediaRequest;
use App\Http\Requests\Multimedia\UpdateGalleryOrderRequest;
use App\Http\Requests\Multimedia\UpdateMultimediaRequest;
use App\Models\Category;
use App\Models\Module;
use App\Models\Multimedia;
use App\Models\MultimediaGalleryItem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

class MultimediaController extends Controller
{
    public function index(MultimediaDataTable $dataTable)
    {
        $this->authorize('viewAny', Multimedia::class);

        $categories = $this->getMultimediaCategories();
        $tags = Tag::getWithType('general')->sortBy('name')->values();
        $galleryTags = Tag::getWithType('gallery')->sortBy('name')->values();
        $statusOptions = MultimediaStatus::cases();
        $visibilityOptions = MultimediaVisibility::cases();

        // If AJAX request for cards view
        if (request()->ajax() && request()->get('view') === 'cards')
        {
            return $this->getCardsData();
        }

        return $dataTable->render('multimedia.index', compact(
            'categories',
            'tags',
            'galleryTags',
            'statusOptions',
            'visibilityOptions',
        ));
    }

    private function getCardsData()
    {
        $query = Multimedia::query()->with(['category', 'tags', 'media']);
        $request = request();

        // Apply filters (same as DataTable)
        if ($request->filled('category_id'))
        {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->filled('status'))
        {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('visibility'))
        {
            $query->where('visibility', $request->get('visibility'));
        }

        if ($request->filled('type'))
        {
            $query->where('type', $request->get('type'));
        }

        $search = $request->input('search.value', $request->input('search'));
        if (is_string($search) && $search !== '')
        {
            $query->where(function ($q) use ($search)
            {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Handle tags array (from frontend multi-select)
        if ($request->filled('tags') && is_array($request->get('tags')))
        {
            $tagNames = $request->get('tags');
            $locale = app()->getLocale();
            $query->whereHas('tags', function ($tagQuery) use ($tagNames, $locale)
            {
                $tagQuery->whereIn("name->{$locale}", $tagNames)
                    ->where('type', 'general');
            });
        }

        // Handle galleries array (from frontend multi-select)
        if ($request->filled('galleries') && is_array($request->get('galleries')))
        {
            $galleryNames = $request->get('galleries');
            $locale = app()->getLocale();
            $query->whereHas('tags', function ($tagQuery) use ($galleryNames, $locale)
            {
                $tagQuery->whereIn("name->{$locale}", $galleryNames)
                    ->where('type', 'gallery');
            });
        }

        // Legacy single tag_id support (for backward compatibility)
        if ($request->filled('tag_id'))
        {
            $tagId = $request->get('tag_id');
            $query->whereHas('tags', function ($tagQuery) use ($tagId)
            {
                $tagQuery->where('tags.id', $tagId)
                    ->where('tags.type', 'general');
            });
        }

        // Legacy single gallery_tag_id support (for backward compatibility)
        if ($request->filled('gallery_tag_id'))
        {
            $tagId = $request->get('gallery_tag_id');
            $query->whereHas('tags', function ($tagQuery) use ($tagId)
            {
                $tagQuery->where('tags.id', $tagId)
                    ->where('tags.type', 'gallery');
            });
        }

        $perPage = $request->get('per_page', 12);
        $multimedia = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $cards = $multimedia->map(function ($item)
        {
            $previewUrl = $item->getFirstMediaUrl('poster')
                ?: $item->getFirstMediaUrl('media', 'poster')
                ?: $item->getFirstMediaUrl('media', 'thumb')
                ?: $item->getFirstMediaUrl('media');

            $icon = match ($item->type)
            {
                'image' => 'ti ti-photo',
                'video' => 'ti ti-video',
                'audio' => 'ti ti-music',
                'pdf' => 'ti ti-file-type-pdf',
                default => 'ti ti-file',
            };

            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'type' => $item->type,
                'preview_url' => $previewUrl,
                'icon' => $icon,
                'category' => $item->category?->name,
                'status' => $item->status?->label(),
                'status_value' => $item->status?->value,
                'visibility' => $item->visibility?->label(),
                'visibility_value' => $item->visibility?->value,
                'tags' => $item->tags->where('type', 'general')->pluck('name')->toArray(),
                'created_at' => $item->created_at?->format('d-m-Y H:i'),
                'can_view' => auth()->user()?->can('view', $item) ?? false,
                'can_update' => auth()->user()?->can('update', $item) ?? false,
                'can_delete' => auth()->user()?->can('delete', $item) ?? false,
                'media_url' => $item->getFirstMediaUrl('media'),
            ];
        });

        return response()->json([
            'cards' => $cards,
            'pagination' => [
                'current_page' => $multimedia->currentPage(),
                'last_page' => $multimedia->lastPage(),
                'per_page' => $multimedia->perPage(),
                'total' => $multimedia->total(),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Multimedia::class);

        $categories = $this->getMultimediaCategories();
        $tags = Tag::getWithType('general')->sortBy('name')->values();
        $galleryTags = Tag::getWithType('gallery')->sortBy('name')->values();
        $statusOptions = MultimediaStatus::cases();
        $visibilityOptions = MultimediaVisibility::cases();

        return view('multimedia.form', compact(
            'categories',
            'tags',
            'galleryTags',
            'statusOptions',
            'visibilityOptions',
        ));
    }

    public function store(StoreMultimediaRequest $request)
    {
        $this->authorize('create', Multimedia::class);

        $files = $request->file('files', []);
        $createdCount = 0;
        $uploadedItems = [];

        foreach ($files as $file)
        {
            $title = $this->resolveTitle($request->input('title'), $file, count($files) > 1);
            $type = $this->inferType($file->getMimeType());

            $multimedia = Multimedia::create([
                'team_id' => auth()->user()->currentTeam->id,
                'category_id' => $request->input('category_id') ?: null,
                'title' => $title,
                'description' => $request->input('description'),
                'status' => (int) ($request->input('status') ?? MultimediaStatus::UNCLASSIFIED->value),
                'visibility' => (int) ($request->input('visibility') ?? MultimediaVisibility::PUBLIC->value),
                'type' => $type,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $mediaAdder = $multimedia->addMedia($file);
            $mediaAdder->usingName($file->getClientOriginalName())
                ->usingFileName($this->buildStorageFileName($file));
            $mediaAdder->toMediaCollection('media');

            if ($request->file('poster') && count($files) === 1)
            {
                $multimedia->addMedia($request->file('poster'))->toMediaCollection('poster');
            }

            $this->syncTags(
                $multimedia,
                $request->input('tags', []),
                $request->input('galleries', []),
            );

            $createdCount++;
            $uploadedItems[] = $multimedia;
        }

        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson())
        {
            return response()->json([
                'success' => true,
                'message' => trans_choice(':count file uploaded successfully.|:count files uploaded successfully.', $createdCount, ['count' => $createdCount]),
                'count' => $createdCount,
                'items' => $uploadedItems,
            ]);
        }

        return redirect()
            ->route('multimedia.index')
            ->with('success', trans_choice(':count file uploaded successfully.|:count files uploaded successfully.', $createdCount, ['count' => $createdCount]));
    }

    public function edit(Multimedia $multimedia)
    {
        $this->authorize('update', $multimedia);

        // If AJAX request, return JSON
        if (request()->ajax() || request()->wantsJson())
        {
            $selectedTags = $multimedia->tags->where('type', 'general')->pluck('name')->toArray();
            $selectedGalleries = $multimedia->tags->where('type', 'gallery')->pluck('name')->toArray();

            return response()->json([
                'success' => true,
                'multimedia' => [
                    'id' => $multimedia->id,
                    'title' => $multimedia->title,
                    'description' => $multimedia->description,
                    'category_id' => $multimedia->category_id,
                    'status' => $multimedia->status?->value,
                    'visibility' => $multimedia->visibility?->value,
                    'tags' => $selectedTags,
                    'galleries' => $selectedGalleries,
                ],
            ]);
        }

        $categories = $this->getMultimediaCategories();
        $tags = Tag::getWithType('general')->sortBy('name')->values();
        $galleryTags = Tag::getWithType('gallery')->sortBy('name')->values();
        $statusOptions = MultimediaStatus::cases();
        $visibilityOptions = MultimediaVisibility::cases();
        $selectedTags = $multimedia->tags->where('type', 'general')->pluck('name')->values();
        $selectedGalleries = $multimedia->tags->where('type', 'gallery')->pluck('name')->values();

        return view('multimedia.form', compact(
            'multimedia',
            'categories',
            'tags',
            'galleryTags',
            'statusOptions',
            'visibilityOptions',
            'selectedTags',
            'selectedGalleries',
        ));
    }

    public function update(UpdateMultimediaRequest $request, Multimedia $multimedia)
    {
        $this->authorize('update', $multimedia);

        $multimedia->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id') ?: null,
            'status' => (int) $request->input('status'),
            'visibility' => (int) $request->input('visibility'),
        ]);

        if ($request->file('media'))
        {
            $multimedia->clearMediaCollection('media');
            $multimedia->addMedia($request->file('media'))
                ->usingName($request->file('media')->getClientOriginalName())
                ->usingFileName($this->buildStorageFileName($request->file('media')))
                ->toMediaCollection('media');

            $multimedia->update(['type' => $this->inferType($request->file('media')->getMimeType())]);
        }

        if ($request->file('poster'))
        {
            $multimedia->addMedia($request->file('poster'))->toMediaCollection('poster');
        }

        $this->syncTags(
            $multimedia,
            $request->input('tags', []),
            $request->input('galleries', []),
        );

        // If AJAX request, return JSON
        if ($request->ajax() || $request->wantsJson())
        {
            return response()->json([
                'success' => true,
                'message' => __('app.Multimedia updated successfully.'),
            ]);
        }

        return redirect()
            ->route('multimedia.index')
            ->with('success', __('app.Multimedia updated successfully.'));
    }

    public function destroy(Multimedia $multimedia)
    {
        $this->authorize('delete', $multimedia);

        $multimedia->delete();

        return response()->json([
            'success' => __('Multimedia deleted successfully.'),
        ]);
    }

    public function gallery(int $tag)
    {
        $this->authorize('viewAny', Multimedia::class);

        $galleryTag = Tag::where('id', $tag)
            ->where('type', 'gallery')
            ->firstOrFail();

        $items = Multimedia::query()
            ->leftJoin('multimedia_gallery_items as gallery_items', function ($join) use ($galleryTag)
            {
                $join->on('multimedia.id', '=', 'gallery_items.multimedia_id')
                    ->where('gallery_items.tag_id', $galleryTag->id);
            })
            ->whereHas('tags', function ($tagQuery) use ($galleryTag)
            {
                $tagQuery->where('tags.id', $galleryTag->id)
                    ->where('tags.type', 'gallery');
            })
            ->with(['media', 'category'])
            ->select('multimedia.*', 'gallery_items.order as gallery_order')
            ->orderByRaw('gallery_order IS NULL, gallery_order ASC')
            ->get();

        return view('multimedia.gallery', compact('galleryTag', 'items'));
    }

    public function updateGalleryOrder(UpdateGalleryOrderRequest $request)
    {
        $this->authorize('create', Multimedia::class);

        $tagId = (int) $request->input('gallery_tag_id');
        $items = $request->input('items', []);

        foreach ($items as $item)
        {
            MultimediaGalleryItem::updateOrCreate(
                [
                    'multimedia_id' => (int) $item['id'],
                    'tag_id' => $tagId,
                ],
                [
                    'order' => (int) $item['order'],
                ],
            );
        }

        return response()->json([
            'success' => __('Order updated successfully.'),
        ]);
    }

    public function searchTags(Request $request)
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'general');

        // If query is empty or less than 2 chars, return all tags of the specified type (limit 20)
        // This allows the dropdown to show options immediately when opened
        if (strlen($query) < 2)
        {
            $tags = Tag::where('type', $type)
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->filter(function ($tag)
                {
                    // Filter out invalid tag names
                    return $this->isValidTagName($tag->name);
                })
                ->map(function ($tag)
                {
                    return [
                        'name' => $tag->name,
                    ];
                });

            return response()->json($tags->values());
        }

        $tags = Tag::where('type', $type)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->filter(function ($tag)
            {
                // Filter out invalid tag names
                return $this->isValidTagName($tag->name);
            })
            ->map(function ($tag)
            {
                return [
                    'name' => $tag->name,
                ];
            });

        return response()->json($tags->values());
    }

    private function isValidTagName(?string $name): bool
    {
        if (empty($name))
        {
            return false;
        }

        $normalized = trim($name);

        // Filter out placeholder/invalid values including JSON strings
        $invalidValues = ['Todos', 'todos', 'all', 'All', 'null', 'undefined'];
        if (in_array($normalized, $invalidValues, true))
        {
            return false;
        }

        // Also filter out if it's a JSON string containing "Todos"
        if (str_starts_with($normalized, '{') && str_contains($normalized, 'Todos'))
        {
            return false;
        }

        return true;
    }

    private function getMultimediaCategories(): Collection
    {
        $moduleId = Module::where('key', 'multimedia')->value('id');

        if (! $moduleId)
        {
            return collect();
        }

        return Category::where('module_id', $moduleId)
            ->where('status', 1)
            ->where(function ($query)
            {
                $query->whereNull('team_id')
                    ->orWhere('team_id', auth()->user()->currentTeam->id);
            })
            ->orderBy('order')
            ->orderBy('name')
            ->get();
    }

    private function syncTags(Multimedia $multimedia, array $tags, array $galleries): void
    {
        $generalTags = $this->normalizeTagNames($tags);
        $galleryTags = $this->normalizeTagNames($galleries);

        $multimedia->syncTagsWithType($generalTags, 'general');
        $multimedia->syncTagsWithType($galleryTags, 'gallery');

        $this->syncGalleryItems($multimedia);
    }

    private function syncGalleryItems(Multimedia $multimedia): void
    {
        $galleryTagIds = $multimedia->tagsWithType('gallery')->pluck('id');

        if ($galleryTagIds->isEmpty())
        {
            MultimediaGalleryItem::where('multimedia_id', $multimedia->id)->delete();

            return;
        }

        MultimediaGalleryItem::where('multimedia_id', $multimedia->id)
            ->whereNotIn('tag_id', $galleryTagIds)
            ->delete();

        foreach ($galleryTagIds as $tagId)
        {
            $exists = MultimediaGalleryItem::where('multimedia_id', $multimedia->id)
                ->where('tag_id', $tagId)
                ->exists();

            if (! $exists)
            {
                $maxOrder = MultimediaGalleryItem::where('tag_id', $tagId)->max('order');
                $order = is_null($maxOrder) ? 0 : $maxOrder + 1;

                MultimediaGalleryItem::create([
                    'multimedia_id' => $multimedia->id,
                    'tag_id' => $tagId,
                    'order' => $order,
                ]);
            }
        }
    }

    private function normalizeTagNames(array $tags): array
    {
        return collect($tags)
            ->map(function ($tag)
            {
                return trim((string) $tag);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveTitle(?string $inputTitle, UploadedFile $file, bool $isMultiUpload): string
    {
        if ($inputTitle && ! $isMultiUpload)
        {
            return $inputTitle;
        }

        return pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    }

    private function inferType(?string $mimeType): string
    {
        if (! $mimeType)
        {
            return 'file';
        }

        if (str_starts_with($mimeType, 'image/'))
        {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/'))
        {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/'))
        {
            return 'audio';
        }

        return 'document';
    }

    private function buildStorageFileName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($name);
        $extension = strtolower($file->getClientOriginalExtension());

        if ($slug === '')
        {
            $slug = 'file_'.substr(md5((string) microtime()), 0, 8);
        }

        return $slug.'_'.time().'.'.$extension;
    }
}
