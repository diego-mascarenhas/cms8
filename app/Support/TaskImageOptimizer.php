<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class TaskImageOptimizer
{
    public const MAX_EDGE = 1600;

    public function apply(UploadedFile $file, bool $keepOriginal): UploadedFile
    {
        if ($keepOriginal || $file->getMimeType() === 'image/gif')
        {
            return $file;
        }

        $source = $file->getRealPath();
        if ($source === false || ! is_file($source))
        {
            return $file;
        }

        $image = Image::load($source);
        $tooWide = $image->getWidth() > self::MAX_EDGE || $image->getHeight() > self::MAX_EDGE;
        $tooHeavy = ($file->getSize() ?: 0) > 500 * 1024;

        if (! $tooWide && ! $tooHeavy)
        {
            return $file;
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true))
        {
            $extension = 'jpg';
        }

        $temporary = tempnam(sys_get_temp_dir(), 'task-img');
        if ($temporary === false)
        {
            return $file;
        }

        $path = $temporary.'.'.$extension;
        rename($temporary, $path);

        $image->fit(Fit::Max, self::MAX_EDGE, self::MAX_EDGE)
            ->quality(82)
            ->save($path);

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.'.$extension;

        return new UploadedFile($path, $name, mime_content_type($path) ?: $file->getMimeType(), null, true);
    }
}
