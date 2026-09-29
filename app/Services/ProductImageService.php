<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

class ProductImageService
{
    public const DISK = 'public';

    public const PRIMARY_SIZE = 'square';

    /**
     * Market formats: list thumb, 1:1 catalog, 1.91:1 Open Graph, 4:5 feed.
     *
     * @var array<string, array{width: int, height: int}>
     */
    public const SIZES = [
        'thumb' => ['width' => 300, 'height' => 300],
        'square' => ['width' => 1080, 'height' => 1080],
        'landscape' => ['width' => 1200, 'height' => 630],
        'portrait' => ['width' => 1080, 'height' => 1350],
    ];

    /**
     * @return array{image: string, original: array<string, mixed>, sizes: list<array<string, mixed>>}
     */
    public function store(Team $team, UploadedFile $file, ?string $name = null): array
    {
        $slug = $this->slugFromName($name, $file);
        $extension = $this->extension($file);
        $directory = Str::uuid()->toString();
        $folder = 'shop/products/'.$team->id.'/'.$directory;

        $originalFilename = $slug.'.'.$extension;
        $originalPath = $file->storeAs($folder, $originalFilename, self::DISK);

        if ($originalPath === false)
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo guardar la imagen.')],
            ]);
        }

        $source = Storage::disk(self::DISK)->path($originalPath);
        $sizes = [];

        foreach (self::SIZES as $key => $size)
        {
            $filename = $this->filename($slug, $key, $size['width'], $size['height'], $extension);
            $relative = $folder.'/'.$filename;
            $destination = Storage::disk(self::DISK)->path($relative);

            Image::load($source)
                ->fit(Fit::Crop, $size['width'], $size['height'])
                ->save($destination);

            $sizes[] = $this->variant($key, $size['width'], $size['height'], $filename, $relative);
        }

        $primary = collect($sizes)->firstWhere('key', self::PRIMARY_SIZE) ?? $sizes[0];

        return [
            'image' => $primary['url'],
            'original' => [
                'filename' => $originalFilename,
                'path' => $originalPath,
                'url' => $this->publicUrl($originalPath),
            ],
            'sizes' => $sizes,
        ];
    }

    /**
     * Copy a remote or already-local product photo into shop/products/{team}/{directory}
     * and attach each file to the media library. Leaves the product unchanged when the
     * download fails.
     *
     * @return array{status: string, image: ?string}
     */
    public function importProduct(Product $product): array
    {
        $current = trim((string) $product->image);
        if ($current === '')
        {
            return ['status' => 'skipped', 'image' => null];
        }

        $local = $this->localDirectory($current);
        if ($local !== null && $local['team_id'] === (int) $product->team_id)
        {
            $this->sync($product);

            return ['status' => 'registered', 'image' => $current];
        }

        if (preg_match('#^https?://#i', $current) !== 1)
        {
            return ['status' => 'skipped', 'image' => $current];
        }

        $team = Team::query()->find($product->team_id);
        if ($team === null)
        {
            return ['status' => 'failed', 'image' => $current];
        }

        try
        {
            $uploaded = $this->download($current, (string) $product->name);
            $stored = $this->store($team, $uploaded, (string) $product->name);
        } catch (Throwable)
        {
            return ['status' => 'failed', 'image' => $current];
        }

        $product->forceFill(['image' => $stored['image']])->save();

        return ['status' => 'imported', 'image' => $stored['image']];
    }

    /**
     * Attach the files already stored for this product's image URL.
     * Remote or empty images leave no media rows.
     */
    public function sync(Product $product): void
    {
        $local = $this->localDirectory($product->image);
        $ownsLocal = $local !== null && $local['team_id'] === (int) $product->team_id;

        if (! $ownsLocal)
        {
            if ($product->media()->where('collection_name', 'image')->exists())
            {
                $product->clearMediaCollection('image');
            }

            return;
        }

        $current = (string) $product->getFirstMedia('image')?->getCustomProperty('directory');
        if ($current !== '' && $current !== $local['directory'])
        {
            $product->clearMediaCollection('image');
        }

        $folder = 'shop/products/'.$local['team_id'].'/'.$local['directory'];
        foreach (Storage::disk(self::DISK)->files($folder) as $relative)
        {
            $filename = basename($relative);
            $already = $product->media()
                ->where('collection_name', 'image')
                ->where('file_name', $filename)
                ->exists();
            if ($already)
            {
                continue;
            }

            $product->media()->create([
                'collection_name' => 'image',
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'file_name' => $filename,
                'mime_type' => Storage::disk(self::DISK)->mimeType($relative) ?: 'application/octet-stream',
                'disk' => self::DISK,
                'conversions_disk' => self::DISK,
                'size' => Storage::disk(self::DISK)->size($relative),
                'manipulations' => [],
                'custom_properties' => ['directory' => $local['directory']],
                'generated_conversions' => [],
                'responsive_images' => [],
            ]);
        }
    }

    /**
     * @return array{team_id: int, directory: string}|null
     */
    public function localDirectory(?string $image): ?array
    {
        $path = parse_url((string) $image, PHP_URL_PATH) ?: (string) $image;
        $path = ltrim($path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        if (preg_match('#^shop/products/(\d+)/([^/]+)/[^/]+$#', $path, $matches) !== 1)
        {
            return null;
        }

        return [
            'team_id' => (int) $matches[1],
            'directory' => $matches[2],
        ];
    }

    private function download(string $url, string $name): UploadedFile
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Idoneo CMS'])
            ->get($url);

        if (! $response->successful() || $response->body() === '')
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo descargar la imagen.')],
            ]);
        }

        $mime = strtolower(trim(strtok((string) $response->header('Content-Type'), ';')));
        $extension = match ($mime)
        {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $tmp = tempnam(sys_get_temp_dir(), 'shopimg');
        $path = $tmp.'.'.$extension;
        file_put_contents($path, $response->body());
        @unlink($tmp);

        $info = @getimagesize($path);
        if (! is_array($info))
        {
            @unlink($path);

            throw ValidationException::withMessages([
                'file' => [__('El archivo descargado no es una imagen.')],
            ]);
        }

        $filename = Str::slug($name) ?: 'producto';

        return new UploadedFile($path, $filename.'.'.$extension, $mime !== '' ? $mime : 'image/jpeg', null, true);
    }

    /**
     * Rebuild size URLs from a stored primary image (`{slug}-square-1080x1080.jpg`).
     *
     * @return list<array<string, mixed>>
     */
    public function variantsForUrl(?string $imageUrl): array
    {
        if (! is_string($imageUrl) || $imageUrl === '')
        {
            return [];
        }

        foreach (self::SIZES as $key => $size)
        {
            $needle = '-'.$this->token($key, $size['width'], $size['height']).'.';
            if (! str_contains($imageUrl, $needle))
            {
                continue;
            }

            $variants = [];
            foreach (self::SIZES as $sizeKey => $variant)
            {
                $url = str_replace($needle, '-'.$this->token($sizeKey, $variant['width'], $variant['height']).'.', $imageUrl);
                $filename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
                $variants[] = [
                    'key' => $sizeKey,
                    'label' => $this->label($sizeKey),
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'filename' => $filename,
                    'url' => $url,
                ];
            }

            return $variants;
        }

        return [];
    }

    /**
     * Path or URL the WhatsApp gateway can send. Remote catalog photos stay as https.
     */
    public function whatsAppPath(?string $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '')
        {
            return null;
        }

        if (preg_match('#^https?://#i', $image) === 1)
        {
            return $image;
        }

        $path = ltrim($image, '/');
        if (str_starts_with($path, 'storage/'))
        {
            $relative = substr($path, strlen('storage/'));
            if ($relative !== '' && Storage::disk(self::DISK)->exists($relative))
            {
                return 'storage/'.$relative;
            }

            return null;
        }

        if ($path !== '' && Storage::disk(self::DISK)->exists($path))
        {
            return 'storage/'.$path;
        }

        return null;
    }

    public static function hint(): string
    {
        return __('JPG, PNG or WebP. Generates thumb, square, landscape and portrait crops.');
    }

    private function slugFromName(?string $name, UploadedFile $file): string
    {
        $fromName = Str::slug(trim((string) $name));
        if ($fromName !== '')
        {
            return $fromName;
        }

        $fromFile = Str::slug((string) pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return $fromFile !== '' ? $fromFile : 'producto';
    }

    private function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function filename(string $slug, string $key, int $width, int $height, string $extension): string
    {
        return $slug.'-'.$this->token($key, $width, $height).'.'.$extension;
    }

    private function token(string $key, int $width, int $height): string
    {
        return $key.'-'.$width.'x'.$height;
    }

    private function label(string $key): string
    {
        return match ($key)
        {
            'thumb' => __('Thumb'),
            'square' => __('Square'),
            'landscape' => __('Landscape'),
            'portrait' => __('Portrait'),
            default => $key,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function variant(string $key, int $width, int $height, string $filename, string $path): array
    {
        return [
            'key' => $key,
            'label' => $this->label($key),
            'width' => $width,
            'height' => $height,
            'filename' => $filename,
            'path' => $path,
            'url' => $this->publicUrl($path),
        ];
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
