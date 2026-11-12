<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SurveyCertificate
{
    public function downloadFilename(Survey $survey, SurveyResponse $response): string
    {
        $survey->loadMissing('team');

        $team = str($survey->team?->name ?: 'team')->slug()->toString();
        $person = str($response->user?->name ?: 'participante')->slug()->toString();
        $event = str($survey->code ?: $survey->title)->slug()->limit(40, '')->toString();

        if ($team === '')
        {
            $team = 'team-'.$survey->team_id;
        }

        if ($person === '')
        {
            $person = 'participante';
        }

        if ($event === '')
        {
            $event = 'evento-'.$survey->id;
        }

        return $team.'-'.$event.'-'.$person.'-'.$response->id.'.jpg';
    }

    public function jpeg(Survey $survey, SurveyResponse $response): string
    {
        $name = $response->user?->name;

        if (! $response->certified || blank($name))
        {
            throw new RuntimeException('No hay un certificado para ese email en esta conferencia.');
        }

        if (! function_exists('imagecreatefromjpeg') || ! function_exists('imagecreatefrompng') || ! function_exists('imagettftext'))
        {
            throw new RuntimeException('GD is required to build the certificate.');
        }

        $source = $this->template($survey);
        $image = $this->imageFromFile($source);

        if ($image === false)
        {
            throw new RuntimeException('No se pudo leer la plantilla del certificado.');
        }

        $navy = imagecolorallocate($image, 15, 45, 82);
        $font = $this->font();
        $width = imagesx($image);
        $height = imagesy($image);
        $size = $this->fontSize($font, $name, (int) ($width * 0.62));
        $box = imagettfbbox($size, 0, $font, $name);
        $textWidth = abs($box[2] - $box[0]);
        $x = (int) (($width - $textWidth) / 2);
        $y = $this->baseline($image, $box, $height);

        imagettftext($image, $size, 0, $x, $y, $navy, $font, $name);

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if ($bytes === false || $bytes === '')
        {
            throw new RuntimeException('No se pudo generar el certificado.');
        }

        return $bytes;
    }

    public function cacheTemplate(Survey $survey): string
    {
        return $this->template($survey);
    }

    public function cachedPath(string $url, ?int $surveyId = null): string
    {
        $directory = storage_path('app/survey-certificates');

        if (! is_dir($directory))
        {
            mkdir($directory, 0755, true);
        }

        if ($surveyId)
        {
            foreach (['.jpg', '.jpeg', '.png'] as $extension)
            {
                $bySurvey = $directory.'/'.$surveyId.$extension;

                if (is_file($bySurvey))
                {
                    return $bySurvey;
                }
            }
        }

        $extension = $this->extensionForUrl($url);

        return $directory.'/'.sha1($url).$extension;
    }

    private function template(Survey $survey): string
    {
        $url = (string) $survey->certificate_url;

        if ($url === '')
        {
            throw new RuntimeException('Esta conferencia no tiene plantilla de certificado.');
        }

        if (is_file($url))
        {
            return $url;
        }

        $local = $this->localMediaPath($url);

        if ($local !== null)
        {
            return $local;
        }

        $path = $this->cachedPath($url, $survey->id);

        if (is_file($path))
        {
            return $path;
        }

        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; IdoneoCertificate/1.0)',
                'Accept' => 'image/jpeg,image/png,image/*;q=0.8,*/*;q=0.5',
                'Referer' => rtrim((string) config('app.legacy_media_base'), '/').'/',
            ])
            ->get($url);

        if (! $response->successful() || ! $this->isImagePayload($response->body()))
        {
            throw new RuntimeException('No se pudo descargar la plantilla del certificado.');
        }

        file_put_contents($path, $response->body());

        $bySurvey = storage_path('app/survey-certificates/'.$survey->id.$this->extensionForUrl($url));

        if ($bySurvey !== $path)
        {
            @copy($path, $bySurvey);
        }

        return $path;
    }

    private function imageFromFile(string $path): \GdImage|false
    {
        $bytes = @file_get_contents($path);

        if ($bytes === false || $bytes === '')
        {
            return false;
        }

        if (str_starts_with($bytes, "\xFF\xD8"))
        {
            return imagecreatefromjpeg($path);
        }

        if (str_starts_with($bytes, "\x89PNG"))
        {
            return imagecreatefrompng($path);
        }

        return false;
    }

    private function isImagePayload(string $body): bool
    {
        return str_starts_with($body, "\xFF\xD8") || str_starts_with($body, "\x89PNG");
    }

    private function extensionForUrl(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.png') ? '.png' : '.jpg';
    }

    private function localMediaPath(string $url): ?string
    {
        $base = rtrim((string) config('app.legacy_media_path'), '/');

        if ($base === '' || ! is_dir($base))
        {
            return null;
        }

        $mediaBase = rtrim((string) config('app.legacy_media_base'), '/');
        $prefix = $mediaBase.'/';

        if (! str_starts_with($url, $prefix))
        {
            return null;
        }

        $relative = ltrim(substr($url, strlen($prefix)), '/');
        $path = $base.'/'.$relative;

        return is_file($path) ? $path : null;
    }

    /**
     * @param  array<int, int>  $textBox
     */
    private function baseline(\GdImage $image, array $textBox, int $height): int
    {
        $slot = $this->nameSlot($image);
        $ascent = abs($textBox[7]);
        $descent = max(0, $textBox[1]);

        if ($slot === null)
        {
            return (int) ($height * 0.45);
        }

        return (int) (($slot[0] + $slot[1] + $ascent - $descent) / 2);
    }

    /**
     * Top and bottom of the empty name box, when the template draws one.
     *
     * @return array{0: int, 1: int}|null
     */
    private function nameSlot(\GdImage $image): ?array
    {
        $x = (int) (imagesx($image) / 2);
        $limit = (int) (imagesy($image) * 0.7);
        $bands = [];
        $start = null;

        for ($y = 0; $y < $limit; $y++)
        {
            if ($this->isTan(imagecolorat($image, $x, $y)))
            {
                $start ??= $y;

                continue;
            }

            if ($start !== null)
            {
                $bands[] = [$start, $y - 1];
                $start = null;
            }
        }

        for ($index = 0; $index < count($bands) - 1; $index++)
        {
            $gap = $bands[$index + 1][0] - $bands[$index][1];

            if ($gap >= 60 && $gap <= 140)
            {
                return [$bands[$index][1], $bands[$index + 1][0]];
            }
        }

        return null;
    }

    private function isTan(int $color): bool
    {
        $red = ($color >> 16) & 255;
        $green = ($color >> 8) & 255;
        $blue = $color & 255;

        return $red > 180
            && $green > 160
            && $blue > 140
            && $blue < 240
            && ($red - $blue) > 10
            && ($red - $blue) < 90;
    }

    private function font(): string
    {
        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        foreach ($candidates as $candidate)
        {
            if (is_file($candidate))
            {
                return $candidate;
            }
        }

        throw new RuntimeException('No TrueType font is available for the certificate.');
    }

    private function fontSize(string $font, string $name, int $maxWidth): int
    {
        $size = 32;

        while ($size > 16)
        {
            $box = imagettfbbox($size, 0, $font, $name);
            if (abs($box[2] - $box[0]) <= $maxWidth)
            {
                return $size;
            }

            $size -= 2;
        }

        return $size;
    }
}
