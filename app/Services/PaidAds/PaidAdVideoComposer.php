<?php

namespace App\Services\PaidAds;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class PaidAdVideoComposer
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    /**
     * @param  array<int, array{image: string, audio: string, caption: string}>  $scenes
     */
    public function compose(array $scenes, string $outputPath): void
    {
        if ($scenes === [])
        {
            throw new RuntimeException(__('No se pudo montar el vídeo.'));
        }

        $directory = dirname($outputPath);
        $segments = [];

        foreach (array_values($scenes) as $index => $scene)
        {
            $captionPath = $directory.'/caption-'.$index.'.png';
            $segmentPath = $directory.'/segment-'.$index.'.mp4';
            $this->renderCaption((string) $scene['caption'], $captionPath);
            $this->run($this->segmentArguments($scene, $captionPath, $segmentPath));
            $segments[] = $segmentPath;
        }

        if (count($segments) === 1)
        {
            if (! copy($segments[0], $outputPath))
            {
                throw new RuntimeException(__('No se pudo montar el vídeo.'));
            }

            return;
        }

        $listPath = $directory.'/concat.txt';
        $lines = array_map(function (string $path): string
        {
            return "file '".str_replace("'", "'\\''", $path)."'";
        }, $segments);
        file_put_contents($listPath, implode("\n", $lines)."\n");

        $this->run([
            $this->binary(),
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listPath,
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ]);
    }

    /**
     * @param  array{image: string, audio: string, caption: string}  $scene
     * @return array<int, string>
     */
    public function segmentArguments(array $scene, string $captionPath, string $output): array
    {
        return [
            $this->binary(),
            '-y',
            '-framerate', '30',
            '-loop', '1',
            '-i', $scene['image'],
            '-i', $scene['audio'],
            '-framerate', '30',
            '-loop', '1',
            '-i', $captionPath,
            '-filter_complex', '[0:v]scale='.self::WIDTH.':'.self::HEIGHT.':force_original_aspect_ratio=increase,crop='.self::WIDTH.':'.self::HEIGHT.',fps=30[bg];[bg][2:v]overlay=0:H-h:format=auto[v]',
            '-map', '[v]',
            '-map', '1:a',
            '-c:v', 'libx264',
            '-tune', 'stillimage',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '160k',
            '-ar', '44100',
            '-ac', '2',
            '-shortest',
            '-movflags', '+faststart',
            $output,
        ];
    }

    public function renderCaption(string $caption, string $path): void
    {
        if (! function_exists('imagettftext') || ! function_exists('imagepng'))
        {
            throw new RuntimeException(__('No se pudo montar el vídeo.'));
        }

        $font = $this->fontPath();
        $fontSize = 78;
        $maxWidth = self::WIDTH - 128;
        $lines = $this->wrapLines($caption, $font, $fontSize, $maxWidth);

        while ($fontSize > 46 && $this->widestLine($lines, $font, $fontSize) > $maxWidth)
        {
            $fontSize -= 4;
            $lines = $this->wrapLines($caption, $font, $fontSize, $maxWidth);
        }

        $lineHeight = (int) round($fontSize * 1.2);
        $textHeight = max(1, count($lines)) * $lineHeight;
        $cardHeight = max(360, $textHeight + 180);
        $image = imagecreatetruecolor(self::WIDTH, $cardHeight);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, $cardHeight - 1, $clear);
        imagealphablending($image, true);

        for ($row = 0; $row < $cardHeight; $row++)
        {
            $fade = $row / max(1, $cardHeight - 1);
            $alpha = (int) round(110 - (55 * $fade));
            $shade = imagecolorallocatealpha($image, 12, 16, 20, max(40, min(120, $alpha)));
            imageline($image, 0, $row, self::WIDTH - 1, $row, $shade);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 8, 10, 14);
        $top = (int) (($cardHeight - $textHeight) / 2) + $fontSize;
        $strokes = [[-3, 0], [3, 0], [0, -3], [0, 3], [-2, -2], [2, -2], [-2, 2], [2, 2]];

        foreach ($lines as $index => $line)
        {
            $box = imagettfbbox($fontSize, 0, $font, $line);
            $textWidth = abs($box[2] - $box[0]);
            $x = (int) ((self::WIDTH - $textWidth) / 2);
            $y = $top + ($index * $lineHeight);

            foreach ($strokes as [$dx, $dy])
            {
                imagettftext($image, $fontSize, 0, $x + $dx, $y + $dy, $ink, $font, $line);
            }

            imagettftext($image, $fontSize, 0, $x, $y, $white, $font, $line);
        }

        if (! imagepng($image, $path))
        {
            imagedestroy($image);

            throw new RuntimeException(__('No se pudo montar el vídeo.'));
        }

        imagedestroy($image);
    }

    public function binary(): string
    {
        $configured = config('services.ffmpeg.path');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            '/opt/homebrew/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/usr/bin/ffmpeg',
        ]);

        foreach ($candidates as $path)
        {
            if (is_executable($path))
            {
                return $path;
            }
        }

        $found = $this->which('ffmpeg');
        if ($found !== null)
        {
            return $found;
        }

        throw new RuntimeException(__('No se pudo montar el vídeo.'));
    }

    public function fontPath(): string
    {
        $configured = config('services.ffmpeg.font');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
        ]);

        foreach ($candidates as $path)
        {
            if (is_file($path))
            {
                return $path;
            }
        }

        throw new RuntimeException(__('No se pudo montar el vídeo.'));
    }

    /**
     * @return array<int, string>
     */
    private function wrapLines(string $caption, string $font, int $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($caption)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word)
        {
            if (! is_string($word) || $word === '')
            {
                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($this->textWidth($candidate, $font, $fontSize) > $maxWidth && $current !== '')
            {
                $lines[] = $current;
                $current = $word;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '')
        {
            $lines[] = $current;
        }

        return array_slice($lines === [] ? [trim($caption)] : $lines, 0, 4);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function widestLine(array $lines, string $font, int $fontSize): int
    {
        $widest = 0;
        foreach ($lines as $line)
        {
            $widest = max($widest, $this->textWidth($line, $font, $fontSize));
        }

        return $widest;
    }

    private function textWidth(string $text, string $font, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $font, $text);
        if ($box === false)
        {
            return self::WIDTH;
        }

        return abs($box[2] - $box[0]);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(180);
        $process->run();

        if ($process->isSuccessful())
        {
            return;
        }

        Log::error('PaidAdVideoComposer ffmpeg failed', [
            'error' => mb_substr($process->getErrorOutput(), -2000),
        ]);

        throw new RuntimeException(__('No se pudo montar el vídeo.'));
    }

    private function which(string $binary): ?string
    {
        $process = new Process(['/usr/bin/which', $binary]);
        $process->setTimeout(5);
        $process->run();
        $path = trim($process->getOutput());

        if ($process->isSuccessful() && $path !== '' && is_executable($path))
        {
            return $path;
        }

        return null;
    }
}
