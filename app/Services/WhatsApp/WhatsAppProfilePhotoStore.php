<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WhatsAppProfilePhotoStore
{
    private const DISK = 'public';

    private const FRESH_SECONDS = 604800;

    private const ATTEMPT_SECONDS = 600;

    public function relativePath(int $teamId, string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return 'whatsapp/avatars/'.$teamId.'/'.$digits.'.jpg';
    }

    public function exists(int $teamId, string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '')
        {
            return false;
        }

        return Storage::disk(self::DISK)->exists($this->relativePath($teamId, $digits));
    }

    public function isFresh(int $teamId, string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '' || ! $this->exists($teamId, $digits))
        {
            return false;
        }

        $mtime = Storage::disk(self::DISK)->lastModified($this->relativePath($teamId, $digits));

        return $mtime !== false && (time() - $mtime) < self::FRESH_SECONDS;
    }

    public function publicUrl(int $teamId, string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '' || ! $this->exists($teamId, $digits))
        {
            return null;
        }

        $relative = $this->relativePath($teamId, $digits);
        $mtime = Storage::disk(self::DISK)->lastModified($relative);

        return asset('storage/'.$relative).($mtime ? '?t='.$mtime : '');
    }

    private function attemptKey(int $teamId, string $digits): string
    {
        return 'wa-avatar-fetch:'.$teamId.':'.$digits;
    }

    private function wasRecentlyAttempted(int $teamId, string $digits): bool
    {
        return Cache::has($this->attemptKey($teamId, $digits));
    }

    private function markAttempted(int $teamId, string $digits): void
    {
        Cache::put($this->attemptKey($teamId, $digits), 1, self::ATTEMPT_SECONDS);
    }

    public function storeFromBase64(int $teamId, string $phone, string $base64, ?string $contentType = null): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '')
        {
            return null;
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '')
        {
            return null;
        }

        $mime = is_string($contentType) ? strtolower(trim(explode(';', $contentType)[0])) : '';
        if ($mime !== '' && ! str_starts_with($mime, 'image/'))
        {
            return null;
        }

        $relative = $this->relativePath($teamId, $digits);
        Storage::disk(self::DISK)->put($relative, $decoded);
        $this->publishToPublicLink($relative, $decoded);

        return $this->publicUrl($teamId, $digits);
    }

    public function storeFromUrl(int $teamId, string $phone, string $url): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '' || trim($url) === '')
        {
            return null;
        }

        try
        {
            $response = Http::timeout(8)->get($url);
        } catch (\Throwable)
        {
            return null;
        }

        if (! $response->successful() || $response->body() === '')
        {
            return null;
        }

        $contentType = $response->header('Content-Type');

        return $this->storeFromBase64($teamId, $digits, base64_encode($response->body()), $contentType);
    }

    private function publishToPublicLink(string $relative, string $contents): void
    {
        if (app()->environment('testing'))
        {
            return;
        }

        $target = public_path('storage/'.$relative);
        $directory = dirname($target);
        if (! File::isDirectory($directory))
        {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($target, $contents);
    }

    /**
     * @param  array<int, string>  $phones
     */
    public function hydrateFromGateway(LocalWhatsAppGateway $gateway, int $teamId, array $phones): void
    {
        $needed = [];
        foreach ($phones as $phone)
        {
            $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';
            if ($digits === '' || $this->isFresh($teamId, $digits) || $this->wasRecentlyAttempted($teamId, $digits))
            {
                continue;
            }

            $needed[] = $digits;
            $this->markAttempted($teamId, $digits);
        }

        $needed = array_values(array_unique($needed));
        if ($needed === [])
        {
            return;
        }

        $pictures = $gateway->fetchProfilePictures(array_slice($needed, 0, 8));
        foreach ($pictures as $digits => $picture)
        {
            if (! is_array($picture))
            {
                continue;
            }

            $base64 = $picture['profile_pic_base64'] ?? null;
            if (is_string($base64) && $base64 !== '')
            {
                $this->storeFromBase64(
                    $teamId,
                    (string) $digits,
                    $base64,
                    is_string($picture['profile_pic_content_type'] ?? null) ? $picture['profile_pic_content_type'] : 'image/jpeg',
                );

                continue;
            }

            $url = $picture['profile_pic_url'] ?? $picture['url'] ?? null;
            if (is_string($url) && $url !== '')
            {
                $this->storeFromUrl($teamId, (string) $digits, $url);
            }
        }
    }
}
