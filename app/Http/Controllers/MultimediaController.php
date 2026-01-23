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

        return $dataTable->render('multimedia.index', compact(
            'categories',
            'tags',
            'galleryTags',
            'statusOptions',
            'visibilityOptions',
        ));
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
                'category_id' => $request->input('category_id'),
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
            'category_id' => $request->input('category_id'),
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

        if (strlen($query) < 2)
        {
            return response()->json([]);
        }

        $tags = Tag::where('type', $type)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($tag)
            {
                return [
                    'name' => $tag->name,
                ];
            });

        return response()->json($tags);
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
