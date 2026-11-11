<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SurveyCertificate
{
    public function jpeg(Survey $survey, SurveyResponse $response): string
    {
        $name = $response->user?->name;

        if (! $response->certified || blank($name))
        {
            throw new RuntimeException('This respondent has no certificate.');
        }

        if (! function_exists('imagecreatefromjpeg') || ! function_exists('imagettftext'))
        {
            throw new RuntimeException('GD is required to build the certificate.');
        }

        $source = $this->template($survey);
        $image = imagecreatefromjpeg($source);

        if ($image === false)
        {
            throw new RuntimeException('The certificate template could not be read.');
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
            throw new RuntimeException('The certificate could not be written.');
        }

        return $bytes;
    }

    private function template(Survey $survey): string
    {
        $url = (string) $survey->certificate_url;

        if ($url === '')
        {
            throw new RuntimeException('This survey has no certificate template.');
        }

        if (is_file($url))
        {
            return $url;
        }

        $path = storage_path('app/survey-certificates/'.$survey->id.'.jpg');

        if (! is_dir(dirname($path)))
        {
            mkdir(dirname($path), 0755, true);
        }

        if (! is_file($path))
        {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful())
            {
                throw new RuntimeException('The certificate template could not be downloaded.');
            }

            file_put_contents($path, $response->body());
        }

        return $path;
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
