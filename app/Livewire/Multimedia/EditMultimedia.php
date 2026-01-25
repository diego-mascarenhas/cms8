<?php

namespace App\Livewire\Multimedia;

use App\Enums\MultimediaStatus;
use App\Enums\MultimediaVisibility;
use App\Models\Category;
use App\Models\Multimedia;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Tags\Tag;

class EditMultimedia extends Component
{
    use WithFileUploads;

    public $multimediaId;

    public $title = '';

    public $description = '';

    public $status = 0;

    public $visibility = 2;

    public $categoryId = null;

    public $tags = [];

    public $galleries = [];
    
    #[\Livewire\Attributes\On('tags-updated')]
    public function handleTagsUpdated($selected): void
    {
        $this->tags = is_array($selected) ? $selected : [];
    }
    
    #[\Livewire\Attributes\On('galleries-updated')]
    public function handleGalleriesUpdated($selected): void
    {
        $this->galleries = is_array($selected) ? $selected : [];
    }

    public $media;

    public $poster;

    public $show = false;

    #[\Livewire\Attributes\On('openEditMultimedia')]
    public function handleOpenEdit($id = null): void
    {
        // Handle event format: can be array with 'id' key or direct value
        if (is_array($id) && isset($id['id']))
        {
            $id = $id['id'];
        }

        if ($id)
        {
            $this->loadMultimedia((int) $id);
        }
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1,2',
            'visibility' => 'required|in:1,2',
            'categoryId' => 'nullable|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:50',
            'galleries' => 'nullable|array',
            'galleries.*' => 'nullable|string|max:50',
            'media' => 'nullable|file|max:51200',
            'poster' => 'nullable|image|max:10240',
        ];
    }

    protected $messages = [
        'title.required' => 'El título es obligatorio.',
        'status.required' => 'El estado es obligatorio.',
        'visibility.required' => 'La visibilidad es obligatoria.',
    ];

    public function loadMultimedia($id): void
    {
        $multimedia = Multimedia::with(['category', 'tags'])->findOrFail($id);

        if (! auth()->user()->can('update', $multimedia))
        {
            abort(403);
        }

        // #region agent log
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E', 'location' => 'EditMultimedia.php:99', 'message' => 'loadMultimedia - DB values', 'data' => ['multimedia_id' => $multimedia->id, 'db_category_id' => $multimedia->category_id, 'category_object' => $multimedia->category ? ['id' => $multimedia->category->id, 'name' => $multimedia->category->name] : null], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion

        $this->multimediaId = $multimedia->id;
        $this->title = $multimedia->title;
        $this->description = $multimedia->description ?? '';
        $this->status = $multimedia->status?->value ?? 0;
        $this->visibility = $multimedia->visibility?->value ?? 2;
        $this->categoryId = $multimedia->category_id ?: null;
        $this->tags = $multimedia->tags->where('type', 'general')->pluck('name')->toArray();
        $this->galleries = $multimedia->tags->where('type', 'gallery')->pluck('name')->toArray();

        // #region agent log
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E', 'location' => 'EditMultimedia.php:119', 'message' => 'loadMultimedia - After assignment', 'data' => ['this_categoryId' => $this->categoryId], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion

        $this->show = true;

        // Dispatch event to show offcanvas
        // Use $dispatch to ensure it works with Livewire 3
        $this->dispatch('offcanvas:show');
    }

    public function updated($propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    public function update(): void
    {
        // #region agent log
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B', 'location' => 'EditMultimedia.php:122', 'message' => 'update() called - BEFORE validate', 'data' => ['categoryId' => $this->categoryId, 'tags' => $this->tags, 'galleries' => $this->galleries], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion
        
        \Log::info('EditMultimedia::update called', [
            'tags' => $this->tags,
            'galleries' => $this->galleries,
            'categoryId' => $this->categoryId,
        ]);
        
        $this->validate();

        // #region agent log
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C', 'location' => 'EditMultimedia.php:136', 'message' => 'update() - AFTER validate', 'data' => ['categoryId' => $this->categoryId], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion

        $multimedia = Multimedia::findOrFail($this->multimediaId);

        if (! auth()->user()->can('update', $multimedia))
        {
            abort(403);
        }

        // #region agent log
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'B', 'location' => 'EditMultimedia.php:148', 'message' => 'update() - BEFORE DB update', 'data' => ['categoryId_to_save' => $this->categoryId ?: null, 'current_db_category_id' => $multimedia->category_id], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion

        $multimedia->update([
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->categoryId ?: null,
            'status' => (int) $this->status,
            'visibility' => (int) $this->visibility,
            'updated_by' => auth()->id(),
        ]);

        // #region agent log
        $multimedia->refresh();
        file_put_contents('/Users/magoo/Sites/humano/.cursor/debug.log', json_encode(['sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'C', 'location' => 'EditMultimedia.php:162', 'message' => 'update() - AFTER DB update', 'data' => ['saved_category_id' => $multimedia->category_id], 'timestamp' => round(microtime(true) * 1000)]) . "\n", FILE_APPEND);
        // #endregion

        if ($this->media)
        {
            $multimedia->clearMediaCollection('media');
            $multimedia->addMedia($this->media->getRealPath())
                ->usingName($this->media->getClientOriginalName())
                ->usingFileName($this->buildStorageFileName($this->media))
                ->toMediaCollection('media');

            $multimedia->update(['type' => $this->inferType($this->media->getMimeType())]);
        }

        if ($this->poster)
        {
            $multimedia->addMedia($this->poster->getRealPath())->toMediaCollection('poster');
        }

        $this->syncTags($multimedia, is_array($this->tags) ? $this->tags : [], is_array($this->galleries) ? $this->galleries : []);

        \Log::info('EditMultimedia::update completed');
        
        $this->dispatch('multimedia:updated');
        session()->flash('message', __('app.Multimedia updated successfully.'));
        $this->close();
    }

    public function close(): void
    {
        \Log::info('EditMultimedia::close called');
        
        $this->reset(['multimediaId', 'title', 'description', 'status', 'visibility', 'categoryId', 'tags', 'galleries', 'media', 'poster', 'show']);
        $this->resetValidation();
        
        \Log::info('EditMultimedia::close dispatching offcanvas:hide');
        $this->dispatch('offcanvas:hide');
    }

    private function syncTags(Multimedia $multimedia, array $tags, array $galleries): void
    {
        \Log::info('syncTags called', [
            'tags' => $tags,
            'galleries' => $galleries,
        ]);
        
        // Normalize tag names - return array of strings, not Tag objects
        $normalizedTags = array_values(array_filter(array_map(function ($tag)
        {
            return $this->normalizeTagName($tag);
        }, $tags), function ($tag)
        {
            return ! empty($tag);
        }));

        $normalizedGalleries = array_values(array_filter(array_map(function ($gallery)
        {
            return $this->normalizeTagName($gallery);
        }, $galleries), function ($gallery)
        {
            return ! empty($gallery);
        }));

        \Log::info('Normalized tags', [
            'normalizedTags' => $normalizedTags,
            'normalizedGalleries' => $normalizedGalleries,
        ]);

        // Sync general tags - syncTagsWithType expects array of strings (tag names)
        $multimedia->syncTagsWithType($normalizedTags, 'general');

        // Sync gallery tags - syncTagsWithType expects array of strings (tag names)
        $multimedia->syncTagsWithType($normalizedGalleries, 'gallery');
    }

    private function normalizeTagName(?string $name): ?string
    {
        if (empty($name))
        {
            return null;
        }

        $normalized = trim($name);
        
        // Filter out placeholder/invalid values
        $invalidValues = ['Todos', 'todos', 'all', 'All', ''];
        if (in_array($normalized, $invalidValues, true))
        {
            return null;
        }

        return $normalized;
    }

    private function buildStorageFileName($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $hash = Str::random(40);

        return "{$hash}.{$extension}";
    }

    private function inferType(string $mimeType): string
    {
        return match (true)
        {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }

    public function render()
    {
        $moduleId = \App\Models\Module::where('key', 'multimedia')->value('id');

        if (! $moduleId)
        {
            $categories = collect();
        } else
        {
            $categories = Category::where('module_id', $moduleId)
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

        $statusOptions = MultimediaStatus::cases();
        $visibilityOptions = MultimediaVisibility::cases();

        return view('livewire.multimedia.edit-multimedia', [
            'categories' => $categories,
            'statusOptions' => $statusOptions,
            'visibilityOptions' => $visibilityOptions,
        ]);
    }
}
