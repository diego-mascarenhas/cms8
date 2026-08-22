<?php

namespace App\Services\PaidAds;

use App\Enums\AdCreativeFormat;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaidAdCreativeAssetService
{
    /**
     * @return array<string, mixed>
     */
    public function store(Team $team, UploadedFile $file, AdCreativeFormat $format): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('paid-ads/'.$team->id, $filename, 'public');

        if ($path === false)
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo guardar la imagen.')],
            ]);
        }

        $absolute = Storage::disk('public')->path($path);
        $size = @getimagesize($absolute) ?: [null, null];

        return [
            'format' => $format->value,
            'path' => $path,
            'url' => $this->publicUrl($path),
            'width' => $size[0] ? (int) $size[0] : null,
            'height' => $size[1] ? (int) $size[1] : null,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    public function stream(Team $team, string $path): StreamedResponse
    {
        $this->assertOwnedPath($team, $path);

        if (! Storage::disk('public')->exists($path))
        {
            throw ValidationException::withMessages([
                'path' => [__('No encontramos esa imagen.')],
            ]);
        }

        return Storage::disk('public')->response($path);
    }

    public function delete(Team $team, string $path): void
    {
        $this->assertOwnedPath($team, $path);

        if (Storage::disk('public')->exists($path))
        {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $creative
     * @return array<string, mixed>
     */
    public function formatCreative(array $creative): array
    {
        $assets = collect($creative['assets'] ?? [])
            ->filter(fn ($asset) => is_array($asset) && isset($asset['format'], $asset['path']))
            ->map(function (array $asset)
            {
                $path = (string) $asset['path'];

                return [
                    'format' => $asset['format'],
                    'path' => $path,
                    'url' => $this->publicUrl($path),
                    'width' => isset($asset['width']) ? (int) $asset['width'] : null,
                    'height' => isset($asset['height']) ? (int) $asset['height'] : null,
                    'original_name' => $asset['original_name'] ?? null,
                ];
            })
            ->values()
            ->all();

        return array_merge($creative, ['assets' => $assets]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function formats(): array
    {
        return collect(AdCreativeFormat::cases())
            ->map(fn (AdCreativeFormat $format) => $format->toLookup())
            ->values()
            ->all();
    }

    private function assertOwnedPath(Team $team, string $path): void
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = 'paid-ads/'.$team->id.'/';

        if ($normalized !== $path || str_contains($normalized, '..') || ! str_starts_with($normalized, $prefix))
        {
            throw ValidationException::withMessages([
                'path' => [__('Esa pieza no pertenece a este equipo.')],
            ]);
        }
    }

    private function publicUrl(string $path): string
    {
        $request = request();
        if ($request)
        {
            return $request->getSchemeAndHttpHost().'/storage/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url($path);
    }
}
