<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class BrandLogoService
{
    public const DISK = 'public';

    /**
     * @return array{logo: string}
     */
    public function store(Team $team, Brand $brand, UploadedFile $file): array
    {
        $extension = $this->extension($file);
        $folder = 'shop/brands/'.$team->id.'/'.$brand->id;
        $filename = 'logo.'.$extension;

        Storage::disk(self::DISK)->deleteDirectory($folder);

        $path = $file->storeAs($folder, $filename, self::DISK);
        if ($path === false)
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo guardar el logo.')],
            ]);
        }

        Image::load(Storage::disk(self::DISK)->path($path))
            ->fit(Fit::Contain, 512, 512)
            ->save(Storage::disk(self::DISK)->path($path));

        $url = $this->publicUrl($path);
        $brand->forceFill(['logo' => $url])->save();

        return ['logo' => $url];
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png'));

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
