<?php

namespace Tests\Unit;

use App\Support\TaskImageOptimizer;
use Illuminate\Http\UploadedFile;
use Spatie\Image\Image;
use Tests\TestCase;

class TaskImageOptimizerTest extends TestCase
{
    public function test_shrinks_a_large_image_unless_the_original_is_requested(): void
    {
        if (! function_exists('imagejpeg'))
        {
            $this->markTestSkipped('GD jpeg is required.');
        }

        $path = tempnam(sys_get_temp_dir(), 'task-src').'.jpg';
        $canvas = imagecreatetruecolor(2000, 1200);
        imagejpeg($canvas, $path, 90);
        imagedestroy($canvas);

        $file = new UploadedFile($path, 'grande.jpg', 'image/jpeg', null, true);
        $optimizer = new TaskImageOptimizer;

        $resized = $optimizer->apply($file, false);
        $size = Image::load($resized->getRealPath());
        $this->assertLessThanOrEqual(TaskImageOptimizer::MAX_EDGE, $size->getWidth());
        $this->assertLessThanOrEqual(TaskImageOptimizer::MAX_EDGE, $size->getHeight());
        $this->assertNotSame($path, $resized->getRealPath());

        $kept = $optimizer->apply($file, true);
        $this->assertSame(2000, Image::load($kept->getRealPath())->getWidth());

        @unlink($path);
        if ($resized->getRealPath() !== $path)
        {
            @unlink($resized->getRealPath());
        }
    }
}
