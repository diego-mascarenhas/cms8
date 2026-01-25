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

        $this->multimediaId = $multimedia->id;
        $this->title = $multimedia->title;
        $this->description = $multimedia->description ?? '';
        $this->status = $multimedia->status?->value ?? 0;
        $this->visibility = $multimedia->visibility?->value ?? 2;
        $this->categoryId = $multimedia->category_id ?: null;
        $this->tags = $multimedia->tags->where('type', 'general')->pluck('name')->toArray();
        $this->galleries = $multimedia->tags->where('type', 'gallery')->pluck('name')->toArray();

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
        $this->validate();

        $multimedia = Multimedia::findOrFail($this->multimediaId);

        if (! auth()->user()->can('update', $multimedia))
        {
            abort(403);
        }

        $multimedia->update([
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->categoryId ?: null,
            'status' => (int) $this->status,
            'visibility' => (int) $this->visibility,
            'updated_by' => auth()->id(),
        ]);

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

        $this->dispatch('multimedia:updated');
        session()->flash('message', __('app.Multimedia updated successfully.'));
        $this->close();
    }

    public function close(): void
    {
        $this->reset(['multimediaId', 'title', 'description', 'status', 'visibility', 'categoryId', 'tags', 'galleries', 'media', 'poster', 'show']);
        $this->resetValidation();
        $this->dispatch('offcanvas:hide');
    }

    private function syncTags(Multimedia $multimedia, array $tags, array $galleries): void
    {
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
        
        // Filter out placeholder/invalid values including JSON strings
        $invalidValues = ['Todos', 'todos', 'all', 'All', '', 'null', 'undefined'];
        if (in_array($normalized, $invalidValues, true))
        {
            return null;
        }
        
        // Also filter out if it's a JSON string containing "Todos"
        if (str_starts_with($normalized, '{') && str_contains($normalized, 'Todos'))
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
            $mimeType === 'application/pdf' => 'pdf',
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
