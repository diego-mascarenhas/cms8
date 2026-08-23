<?php

namespace App\Services\PaidAds;

use App\Models\Team;
use App\Services\Business\BusinessProfileService;
use App\Services\TokenUsageLogService;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;
use RuntimeException;

class PaidAdImageGenerationService
{
    /**
     * @param  array{scene: string, hook?: string|null, framing?: string|null, query?: string|null, headline?: string|null}  $brief
     * @return array{path: string, url: string, width: int|null, height: int|null, original_name: string, data_url: string}
     */
    public function generate(Team $team, array $brief): array
    {
        $prompt = $this->prompt($brief, $team);

        try
        {
            $response = Image::of($prompt)
                ->square()
                ->quality('medium')
                ->timeout(90)
                ->generate(AiTasks::provider('image'));
        } catch (\Throwable $exception)
        {
            Log::error('PaidAdImageGenerationService failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo generar la imagen. Probá de nuevo.'), 0, $exception);
        }

        $image = $response->firstImage();
        $extension = match ($image->mime)
        {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'paid-ads/'.$team->id;
        $path = $response->storePubliclyAs($directory, $filename, 'public');

        if ($path === false)
        {
            throw new RuntimeException(__('No se pudo guardar la imagen generada.'));
        }

        $absolute = Storage::disk('public')->path($path);
        $size = @getimagesize($absolute) ?: [null, null];
        $mime = $image->mime ?: 'image/png';

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'PaidAdImageGenerationService',
            usage: $response->usage ?? null,
            moduleKey: 'paid_ads',
            inputSize: strlen($prompt),
            outputSize: strlen($image->image),
        );

        return [
            'path' => $path,
            'url' => $this->publicUrl($path),
            'width' => $size[0] ? (int) $size[0] : null,
            'height' => $size[1] ? (int) $size[1] : null,
            'original_name' => 'sugerida-'.$filename,
            'data_url' => 'data:'.$mime.';base64,'.$image->image,
        ];
    }

    /**
     * @param  array{scene: string, hook?: string|null, framing?: string|null, query?: string|null, headline?: string|null}  $brief
     */
    public function prompt(array $brief, ?Team $team = null): string
    {
        $lines = [
            'Photorealistic advertising photograph. No text overlay, no watermarks, no UI chrome. Do not invent a logo.',
            'Scene: '.trim((string) $brief['scene']),
        ];

        if ($team)
        {
            $brand = app(BusinessProfileService::class)->promptAppendix($team);
            if ($brand !== '')
            {
                $lines[] = $brand;
            }
        }

        if (trim((string) ($brief['framing'] ?? '')) !== '')
        {
            $lines[] = 'Framing: '.trim((string) $brief['framing']);
        }
        if (trim((string) ($brief['hook'] ?? '')) !== '')
        {
            $lines[] = 'Mood: '.trim((string) $brief['hook']);
        }
        if (trim((string) ($brief['headline'] ?? '')) !== '')
        {
            $lines[] = 'Campaign headline for mood only, do not write it in the image: '.trim((string) $brief['headline']);
        }

        return implode("\n", $lines);
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
