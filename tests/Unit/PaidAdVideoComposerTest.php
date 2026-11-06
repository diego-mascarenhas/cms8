<?php

namespace Tests\Unit;

use App\Services\PaidAds\PaidAdVideoComposer;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PaidAdVideoComposerTest extends TestCase
{
    public function test_segment_command_scales_to_a_story_and_overlays_the_caption(): void
    {
        $composer = app(PaidAdVideoComposer::class);

        try
        {
            $composer->binary();
        } catch (RuntimeException)
        {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $args = $composer->segmentArguments([
            'image' => '/tmp/still.png',
            'audio' => '/tmp/voice.mp3',
            'caption' => '¿Cuánto ganaste?',
        ], '/tmp/caption.png', '/tmp/out.mp4');

        $command = implode(' ', $args);
        $this->assertStringContainsString('scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920', $command);
        $this->assertStringContainsString('/tmp/caption.png', $command);
        $this->assertStringContainsString('libx264', $command);
    }

    public function test_caption_png_is_story_width(): void
    {
        if (! function_exists('imagettftext'))
        {
            $this->markTestSkipped('GD FreeType is missing.');
        }

        $path = storage_path('framework/testing-paid-ad-caption.png');
        app(PaidAdVideoComposer::class)->renderCaption('¿Cuánto ganaste?', $path);

        $size = getimagesize($path);
        @unlink($path);

        $this->assertIsArray($size);
        $this->assertSame(1080, $size[0]);
        $this->assertGreaterThan(200, $size[1]);
    }

    public function test_compose_builds_an_mp4_from_stills_and_audio(): void
    {
        if (! function_exists('imagettftext') || ! function_exists('imagepng'))
        {
            $this->markTestSkipped('GD is missing.');
        }

        $composer = app(PaidAdVideoComposer::class);

        try
        {
            $composer->binary();
        } catch (RuntimeException)
        {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $directory = storage_path('framework/testing-paid-ad-video');
        if (! is_dir($directory))
        {
            mkdir($directory, 0700, true);
        }

        $image = $directory.'/still.png';
        $canvas = imagecreatetruecolor(40, 60);
        $green = imagecolorallocate($canvas, 31, 92, 69);
        imagefilledrectangle($canvas, 0, 0, 39, 59, $green);
        imagepng($canvas, $image);
        imagedestroy($canvas);

        $audioA = $directory.'/a.wav';
        $audioB = $directory.'/b.wav';
        $this->silentWav($audioA, 0.4);
        $this->silentWav($audioB, 0.4);
        $output = $directory.'/reel.mp4';

        try
        {
            $composer->compose([
                [
                    'image' => $image,
                    'audio' => $audioA,
                    'caption' => '¿Cuánto ganaste?',
                ],
                [
                    'image' => $image,
                    'audio' => $audioB,
                    'caption' => 'Lo vendiste',
                ],
            ], $output);

            $this->assertFileExists($output);
            $this->assertGreaterThan(1000, filesize($output));

            $probe = dirname($composer->binary()).'/ffprobe';
            if (is_executable($probe))
            {
                $process = new Process([$probe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $output]);
                $process->run();
                $this->assertGreaterThan(0.5, (float) trim($process->getOutput()));
            }
        } finally
        {
            foreach (glob($directory.'/*') ?: [] as $file)
            {
                if (is_file($file))
                {
                    unlink($file);
                }
            }
            @rmdir($directory);
        }
    }

    private function silentWav(string $path, float $seconds): void
    {
        $sampleRate = 8000;
        $samples = (int) round($sampleRate * $seconds);
        $data = str_repeat("\0\0", $samples);
        $header = pack(
            'a4Va4a4VvvVVvva4V',
            'RIFF',
            36 + strlen($data),
            'WAVE',
            'fmt ',
            16,
            1,
            1,
            $sampleRate,
            $sampleRate * 2,
            2,
            16,
            'data',
            strlen($data),
        );

        file_put_contents($path, $header.$data);
    }
}
