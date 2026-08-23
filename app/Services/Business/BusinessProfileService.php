<?php

namespace App\Services\Business;

use App\Jobs\LoadTeamBusinessInsightsJob;
use App\Models\Country;
use App\Models\Team;
use App\Support\AiTasks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Laravel\Ai\agent;

class BusinessProfileService
{
    public const MAX_BRAND_IMAGES = 3;

    /** @var list<string> */
    public const EDITABLE_KEYS = [
        'business_name',
        'business_industry',
        'business_location',
        'business_postal_code',
        'business_phone',
        'business_whatsapp',
        'business_website',
        'business_email',
        'contact_email',
        'business_tagline',
        'business_description',
        'business_challenge',
        'first_name',
        'last_name',
        'birth_date',
        'birth_time',
        'address',
        'landmark',
        'pincode',
        'city',
        'country',
        'language',
        'wants_to_deepen',
        'twitter',
        'facebook',
        'instagram',
        'linkedin',
        'youtube',
        'tiktok',
        'whatsapp_url',
        'telegram',
        'pinterest',
        'threads',
    ];

    /**
     * @return array<string, mixed>
     */
    public function payload(Team $team): array
    {
        $config = $this->decode($team);
        $fields = [];

        foreach (self::EDITABLE_KEYS as $key)
        {
            $value = $config[$key] ?? null;
            $fields[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $insights = $config['_insights'] ?? null;
        $requestedAt = $config['_insights_requested_at'] ?? null;

        return array_merge($fields, [
            'configured' => trim((string) ($config['business_name'] ?? '')) !== '',
            'summary' => isset($config['_summary']) && is_string($config['_summary']) ? $config['_summary'] : null,
            'insights' => is_array($insights) ? $insights : null,
            'insights_loading' => is_string($requestedAt) && $requestedAt !== '' && ! is_array($insights),
            'insights_phase' => isset($config['_insights_phase']) && is_string($config['_insights_phase'])
                ? $config['_insights_phase']
                : null,
            'countries' => $this->countryNames(),
            'logo' => $this->formatAsset($config['_logo'] ?? null),
            'images' => $this->formatAssets($config['_brand_images'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(Team $team, array $input): array
    {
        $config = $this->decode($team);

        foreach (self::EDITABLE_KEYS as $key)
        {
            if (! array_key_exists($key, $input))
            {
                continue;
            }

            $value = $input[$key];
            if ($value === null)
            {
                unset($config[$key]);

                continue;
            }

            $text = trim((string) $value);
            if ($text === '')
            {
                unset($config[$key]);

                continue;
            }

            $config[$key] = $text;
        }

        $this->persist($team, $config);

        return $this->payload($team->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function storeAsset(Team $team, UploadedFile $file, string $role): array
    {
        $asset = $this->writeFile($team, $file);
        $config = $this->decode($team);

        if ($role === 'logo')
        {
            $this->deleteStoredPath($config['_logo']['path'] ?? null);
            $config['_logo'] = $asset;
        } else
        {
            $images = $this->assetList($config['_brand_images'] ?? []);
            if (count($images) >= self::MAX_BRAND_IMAGES)
            {
                throw ValidationException::withMessages([
                    'file' => [__('Podés subir hasta :max fotos de marca.', ['max' => self::MAX_BRAND_IMAGES])],
                ]);
            }
            $images[] = $asset;
            $config['_brand_images'] = $images;
        }

        $this->persist($team, $config);

        $formatted = $this->formatAsset($asset);
        $formatted['data_url'] = $this->dataUrl($asset['path']);

        return $formatted;
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

    /**
     * @return array<string, mixed>
     */
    public function deleteAsset(Team $team, string $path): array
    {
        $this->assertOwnedPath($team, $path);
        $config = $this->decode($team);

        if (($config['_logo']['path'] ?? null) === $path)
        {
            unset($config['_logo']);
        }

        $config['_brand_images'] = array_values(array_filter(
            $this->assetList($config['_brand_images'] ?? []),
            fn (array $asset): bool => ($asset['path'] ?? null) !== $path,
        ));

        $this->deleteStoredPath($path);
        $this->persist($team, $config);

        return $this->payload($team->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function storeSummary(Team $team, string $summary): array
    {
        $config = $this->decode($team);
        $config['_summary'] = $summary;
        $this->persist($team, $config);

        return $this->payload($team->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function queueInsights(Team $team): array
    {
        $industry = trim((string) ($this->decode($team)['business_industry'] ?? ''));
        $description = trim((string) ($this->decode($team)['business_description'] ?? ''));
        $tagline = trim((string) ($this->decode($team)['business_tagline'] ?? ''));
        if ($industry === '' || $description === '' || $tagline === '')
        {
            throw ValidationException::withMessages([
                'business_industry' => [__('Completá sector, descripción y propuesta de valor para generar el informe.')],
            ]);
        }

        $config = $this->decode($team);
        $config['_insights_requested_at'] = now()->toIso8601String();
        unset($config['_insights']);
        $this->persist($team, $config);
        LoadTeamBusinessInsightsJob::dispatch($team->id);

        return $this->payload($team->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function generateSummary(Team $team): array
    {
        $config = $this->decode($team);
        $challenge = trim((string) ($config['business_challenge'] ?? ''));
        if ($challenge === '')
        {
            throw ValidationException::withMessages([
                'business_challenge' => [__('Describí el desafío para generar el resumen.')],
            ]);
        }

        $lines = ['Datos del negocio:'];
        foreach ([
            'Nombre' => $config['business_name'] ?? null,
            'Rubro/Sector' => $config['business_industry'] ?? null,
            'Eslogan' => $config['business_tagline'] ?? null,
            'Descripción' => $config['business_description'] ?? null,
            'Página web' => $config['business_website'] ?? null,
        ] as $label => $value)
        {
            $text = trim((string) $value);
            if ($text !== '')
            {
                $lines[] = '- '.$label.': '.$text;
            }
        }

        $userMessage = "Problemática actual del negocio:\n\n".$challenge."\n\n---\n\n".implode("\n", $lines);

        try
        {
            $agent = agent(
                instructions: 'Eres un consultor de negocio. Genera un resumen muy conciso (máximo 1 párrafo corto o 3-5 puntos) de lo que esta empresa necesita para mejorar. Sé directo y práctico.',
                messages: [],
                tools: [],
            );
            $response = $agent->prompt($userMessage, [], AiTasks::provider('summary'));
            $summary = trim((string) ($response->text ?? ''));
        } catch (\Throwable $exception)
        {
            Log::error('BusinessProfileService summary failed', [
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(__('No se pudo generar el resumen. Probá de nuevo.'), 0, $exception);
        }

        if ($summary === '')
        {
            throw new RuntimeException(__('La IA no devolvió un resumen. Probá de nuevo.'));
        }

        return $this->storeSummary($team, $summary);
    }

    public function promptAppendix(Team $team): string
    {
        $payload = $this->payload($team);
        $lines = [];

        foreach ([
            'Marca' => $payload['business_name'],
            'Rubro' => $payload['business_industry'],
            'Propuesta de valor' => $payload['business_tagline'],
            'Descripción' => $payload['business_description'],
            'Sitio web' => $payload['business_website'],
            'Ubicación' => $payload['city'] ?: $payload['business_location'],
        ] as $label => $value)
        {
            if (is_string($value) && $value !== '')
            {
                $lines[] = $label.': '.$value;
            }
        }

        if ($payload['logo'] !== null)
        {
            $lines[] = 'Tiene logo de marca cargado: usalo como referencia de identidad, no inventes otro isotipo.';
        }

        $imageCount = count($payload['images']);
        if ($imageCount > 0)
        {
            $lines[] = 'Hay '.$imageCount.' foto(s) de referencia de la marca. Mantené el estilo visual.';
        }

        if ($lines === [])
        {
            return '';
        }

        return "Contexto del negocio:\n".implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Team $team): array
    {
        $saved = $team->getSetting('business_config', []);
        if (is_string($saved))
        {
            $saved = json_decode($saved, true) ?: [];
        }

        return is_array($saved) ? $saved : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function persist(Team $team, array $config): void
    {
        $team->setSetting('business_config', $config, [
            'type' => 'json',
            'group' => 'business-config',
        ]);
    }

    /**
     * @return array{path: string, width: int|null, height: int|null, original_name: string}
     */
    private function writeFile(Team $team, UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('business/'.$team->id, $filename, 'public');

        if ($path === false)
        {
            throw ValidationException::withMessages([
                'file' => [__('No se pudo guardar la imagen.')],
            ]);
        }

        $absolute = Storage::disk('public')->path($path);
        $size = @getimagesize($absolute) ?: [null, null];

        return [
            'path' => $path,
            'width' => $size[0] ? (int) $size[0] : null,
            'height' => $size[1] ? (int) $size[1] : null,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * @return array{path: string, url: string, width: int|null, height: int|null, original_name: string|null}|null
     */
    private function formatAsset(mixed $asset): ?array
    {
        if (! is_array($asset) || ! isset($asset['path']) || ! is_string($asset['path']) || $asset['path'] === '')
        {
            return null;
        }

        return [
            'path' => $asset['path'],
            'url' => $this->publicUrl($asset['path']),
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
            'original_name' => isset($asset['original_name']) ? (string) $asset['original_name'] : null,
        ];
    }

    /**
     * @return list<array{path: string, url: string, width: int|null, height: int|null, original_name: string|null}>
     */
    private function formatAssets(mixed $assets): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $asset): ?array => $this->formatAsset($asset), $this->assetList($assets)),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assetList(mixed $assets): array
    {
        if (! is_array($assets))
        {
            return [];
        }

        return array_values(array_filter($assets, fn ($asset): bool => is_array($asset)));
    }

    private function assertOwnedPath(Team $team, string $path): void
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $prefix = 'business/'.$team->id.'/';

        if ($normalized !== $path || str_contains($normalized, '..') || ! str_starts_with($normalized, $prefix))
        {
            throw ValidationException::withMessages([
                'path' => [__('Esa imagen no pertenece a este equipo.')],
            ]);
        }
    }

    private function deleteStoredPath(mixed $path): void
    {
        if (! is_string($path) || $path === '')
        {
            return;
        }

        if (Storage::disk('public')->exists($path))
        {
            Storage::disk('public')->delete($path);
        }
    }

    private function dataUrl(string $path): string
    {
        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
    }

    /**
     * @return list<string>
     */
    private function countryNames(): array
    {
        try
        {
            return Country::query()->orderBy('name')->pluck('name')->all();
        } catch (\Throwable)
        {
            return [];
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
