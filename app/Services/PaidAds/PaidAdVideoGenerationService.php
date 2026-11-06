<?php

namespace App\Services\PaidAds;

use App\Enums\AdCreativeFormat;
use App\Jobs\GeneratePaidAdVideoJob;
use App\Models\Team;
use App\Services\TokenUsageLogService;
use App\Support\AiTasks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;
use Laravel\Ai\Image;
use RuntimeException;
use Throwable;

class PaidAdVideoGenerationService
{
    public function __construct(
        private readonly PaidAdVideoComposer $composer,
        private readonly PaidAdImageGenerationService $images,
        private readonly PaidAdCreativeAssetService $assets,
    ) {}

    /**
     * @param  array{headline?: string|null, body?: string|null, voice?: string|null, voice_id?: string|null, scenes: array<int, array{caption: string, spoken: string, visual: string}>}  $payload
     * @return array{id: string, status: string}
     */
    public function start(Team $team, array $payload): array
    {
        $videoId = (string) Str::uuid();
        $this->remember($team->id, $videoId, [
            'status' => 'queued',
            'message' => __('En cola'),
            'asset' => null,
        ]);

        $pending = GeneratePaidAdVideoJob::dispatch($team->id, $videoId, $payload);
        if (config('queue.default') === 'sync')
        {
            $pending->afterResponse();
        }

        return [
            'id' => $videoId,
            'status' => 'queued',
        ];
    }

    /**
     * @return array{id: string, status: string, message: string|null, asset: array<string, mixed>|null}|null
     */
    public function status(int $teamId, string $videoId): ?array
    {
        $cached = Cache::get($this->key($teamId, $videoId));
        if (! is_array($cached))
        {
            return null;
        }

        return [
            'id' => $videoId,
            'status' => (string) ($cached['status'] ?? 'queued'),
            'message' => isset($cached['message']) ? (string) $cached['message'] : null,
            'asset' => is_array($cached['asset'] ?? null) ? $cached['asset'] : null,
        ];
    }

    public function markFailed(int $teamId, string $videoId, string $message): void
    {
        $current = $this->status($teamId, $videoId);
        if (($current['status'] ?? null) === 'failed' && filled($current['message'] ?? null))
        {
            return;
        }

        $this->remember($teamId, $videoId, [
            'status' => 'failed',
            'message' => $message,
            'asset' => null,
        ]);
    }

    /**
     * @param  array{headline?: string|null, body?: string|null, voice?: string|null, voice_id?: string|null, scenes: array<int, array{caption: string, spoken: string, visual: string}>}  $payload
     */
    public function render(int $teamId, string $videoId, array $payload): void
    {
        if (! Str::isUuid($videoId))
        {
            throw new RuntimeException(__('No se pudo generar el vídeo. Probá de nuevo.'));
        }

        ignore_user_abort(true);
        set_time_limit(360);

        $work = sys_get_temp_dir().'/paid-ad-video-'.$videoId;
        if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work))
        {
            throw new RuntimeException(__('No se pudo generar el vídeo. Probá de nuevo.'));
        }

        try
        {
            $team = Team::query()->find($teamId);
            if (! $team)
            {
                throw new RuntimeException(__('No se pudo generar el vídeo. Probá de nuevo.'));
            }

            $scenes = array_values($payload['scenes'] ?? []);
            if (count($scenes) !== 3)
            {
                throw new RuntimeException(__('El guion tiene que tener tres escenas.'));
            }

            $this->remember($teamId, $videoId, [
                'status' => 'processing',
                'message' => __('Preparando las escenas'),
                'asset' => null,
            ]);

            $clips = [];
            foreach ($scenes as $index => $scene)
            {
                $number = $index + 1;
                $this->remember($teamId, $videoId, [
                    'status' => 'processing',
                    'message' => __('Escena :number de 3', ['number' => $number]),
                    'asset' => null,
                ]);

                $imagePath = $work.'/scene-'.$index.'.png';
                $audioPath = $work.'/scene-'.$index.'.mp3';
                $this->writeStill($team, $scene, (string) ($payload['headline'] ?? ''), $imagePath);
                $this->writeVoice($scene['spoken'], (string) ($payload['voice'] ?? 'female'), (string) ($payload['voice_id'] ?? ''), $audioPath);
                $clips[] = [
                    'image' => $imagePath,
                    'audio' => $audioPath,
                    'caption' => $scene['caption'],
                ];
            }

            $this->remember($teamId, $videoId, [
                'status' => 'processing',
                'message' => __('Montando el vídeo'),
                'asset' => null,
            ]);

            $reel = $work.'/reel.mp4';
            $this->composer->compose($clips, $reel);
            $asset = $this->publish($team, $reel);

            $this->remember($teamId, $videoId, [
                'status' => 'ready',
                'message' => null,
                'asset' => $asset,
            ]);
        } catch (Throwable $exception)
        {
            Log::error('PaidAdVideoGenerationService failed', [
                'video_id' => $videoId,
                'error' => $exception->getMessage(),
            ]);

            $this->remember($teamId, $videoId, [
                'status' => 'failed',
                'message' => $this->publicError($exception),
                'asset' => null,
            ]);

            throw $exception;
        } finally
        {
            $this->deleteTree($work);
        }
    }

    /**
     * @param  array{caption: string, spoken: string, visual: string}  $scene
     */
    private function writeStill(Team $team, array $scene, string $headline, string $path): void
    {
        $prompt = $this->images->prompt([
            'scene' => $scene['visual'],
            'hook' => $scene['caption'],
            'framing' => 'Vertical photograph. Subject in the upper two thirds. Leave the lower third uncluttered.',
            'headline' => $headline,
        ], $team);

        $response = Image::of($prompt)
            ->portrait()
            ->quality('medium')
            ->timeout(90)
            ->generate(AiTasks::provider('image'));

        $image = $response->firstImage();
        $bytes = base64_decode((string) $image->image, true);
        if ($bytes === false || file_put_contents($path, $bytes) === false)
        {
            throw new RuntimeException(__('No se pudo generar la imagen. Probá de nuevo.'));
        }

        TokenUsageLogService::logFromAiResponse(
            teamId: (int) $team->id,
            service: 'PaidAdVideoGenerationService',
            usage: $response->usage ?? null,
            moduleKey: 'paid_ads',
            inputSize: strlen($prompt),
            outputSize: strlen((string) $image->image),
        );
    }

    private function writeVoice(string $spoken, string $voice, string $voiceId, string $path): void
    {
        $pending = Audio::of($spoken);
        $voiceId = trim($voiceId);
        if ($voiceId !== '')
        {
            $pending = $pending->voice($voiceId);
        } elseif ($voice === 'male')
        {
            $pending = $pending->male();
        } else
        {
            $pending = $pending->female();
        }

        $response = $pending->generate('eleven', 'eleven_multilingual_v2');
        if (file_put_contents($path, $response->content()) === false)
        {
            throw new RuntimeException(__('No se pudo generar la voz. Revisá la clave de ElevenLabs.'));
        }
    }

    /**
     * @return array{format: string, path: string, url: string, width: int, height: int, original_name: string, media: string}
     */
    private function publish(Team $team, string $reel): array
    {
        $stream = fopen($reel, 'r');
        if ($stream === false)
        {
            throw new RuntimeException(__('No se pudo guardar el vídeo.'));
        }

        $filename = Str::uuid()->toString().'.mp4';
        $stored = 'paid-ads/'.$team->id.'/'.$filename;
        $saved = Storage::disk('public')->writeStream($stored, $stream);
        if (is_resource($stream))
        {
            fclose($stream);
        }

        if ($saved === false)
        {
            throw new RuntimeException(__('No se pudo guardar el vídeo.'));
        }

        return $this->assets->describeStored(
            $team,
            $stored,
            AdCreativeFormat::Story,
            PaidAdVideoComposer::WIDTH,
            PaidAdVideoComposer::HEIGHT,
            'reel.mp4',
        );
    }

    private function publicError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $safe = [
            __('No se pudo generar la imagen. Probá de nuevo.'),
            __('No se pudo montar el vídeo.'),
            __('No se pudo guardar el vídeo.'),
            __('No se pudo generar la voz. Revisá la clave de ElevenLabs.'),
            __('El guion tiene que tener tres escenas.'),
            __('No se pudo generar el vídeo. Probá de nuevo.'),
        ];

        if (in_array($message, $safe, true))
        {
            return $message;
        }

        $lower = strtolower($message);
        if (str_contains($lower, 'elevenlabs') || str_contains($lower, 'eleven') || str_contains($message, '401'))
        {
            return __('No se pudo generar la voz. Revisá la clave de ElevenLabs.');
        }

        return __('No se pudo generar el vídeo. Probá de nuevo.');
    }

    /**
     * @param  array{status: string, message: string|null, asset: array<string, mixed>|null}  $state
     */
    private function remember(int $teamId, string $videoId, array $state): void
    {
        Cache::put($this->key($teamId, $videoId), $state, now()->addHours(6));
    }

    private function key(int $teamId, string $videoId): string
    {
        return 'paid-ad-video:'.$teamId.':'.$videoId;
    }

    private function deleteTree(string $directory): void
    {
        if (! is_dir($directory))
        {
            return;
        }

        $items = scandir($directory);
        if ($items === false)
        {
            return;
        }

        foreach ($items as $item)
        {
            if ($item === '.' || $item === '..')
            {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path))
            {
                $this->deleteTree($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
