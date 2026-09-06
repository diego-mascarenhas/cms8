<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class StoreBannerService
{
    public const DISK = 'public';

    /**
     * Recommended header size ~1920×480 (soft-cropped for display).
     *
     * @return array{banner: string}
     */
    public function store(Team $team, Store $store, UploadedFile $file): array
    {
        $extension = $this->extension($file);
        $folder = 'shop/stores/'.$team->id.'/'.$store->id;
        $filename = 'banner.'.$extension;

        Storage::disk(self::DISK)->deleteDirectory($folder);

        $path = $file->storeAs($folder, $filename, self::DISK);
        if ($path === false)
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo guardar el banner.')],
            ]);
        }

        Image::load(Storage::disk(self::DISK)->path($path))
            ->fit(Fit::Crop, 1920, 480)
            ->save(Storage::disk(self::DISK)->path($path));

        $url = $this->publicUrl($path);
        $data = is_array($store->data) ? $store->data : [];
        $data['banner'] = $url;
        $store->forceFill(['data' => $data])->save();

        return ['banner' => $url];
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function publicUrl(string $path): string
    {
        $request = request();
        if ($request)
        {
            return $request->getSchemeAndHttpHost().'/storage/'.ltrim($path, '/');
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
