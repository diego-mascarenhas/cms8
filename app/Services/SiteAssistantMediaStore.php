<?php

namespace App\Services;

use App\Models\Automation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteAssistantMediaStore
{
    /**
     * @return list<array{url: string, content_type: string, name: string, path: string}>
     */
    public function store(Automation $automation, UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg'));
        $filename = (string) Str::uuid().'.'.$extension;
        $path = 'site-assistant/'.(int) $automation->team_id.'/'.$filename;
        Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

        return [
            [
                'url' => $this->absoluteUrl($path),
                'content_type' => $file->getMimeType() ?: 'image/jpeg',
                'name' => $file->getClientOriginalName() ?: $filename,
                'path' => $path,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $media
     * @return list<array{url: string, content_type: string, name: string}>
     */
    public function present(?array $media): array
    {
        if ($media === null || $media === [])
        {
            return [];
        }

        $items = [];
        foreach ($media as $item)
        {
            if (! is_array($item))
            {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            if ($url === '' && isset($item['path']))
            {
                $url = $this->absoluteUrl((string) $item['path']);
            }
            if ($url === '')
            {
                continue;
            }

            $items[] = [
                'url' => $url,
                'content_type' => (string) ($item['content_type'] ?? 'image/jpeg'),
                'name' => (string) ($item['name'] ?? 'Foto'),
            ];
        }

        return $items;
    }

    private function absoluteUrl(string $path): string
    {
        $relative = Storage::disk('public')->url($path);

        return str_starts_with($relative, 'http') ? $relative : url($relative);
    }
}
