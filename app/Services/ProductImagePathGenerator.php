<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Keeps shop product photos at storage/app/public/shop/products/{team_id}/{directory}/.
 * The directory lives on the media row so the public URL does not move.
 */
class ProductImagePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->directory($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->directory($media);
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->directory($media);
    }

    private function directory(Media $media): string
    {
        $teamId = (int) ($media->model->team_id ?? 0);
        $directory = (string) $media->getCustomProperty('directory');

        if ($directory === '')
        {
            return "shop/products/{$teamId}/";
        }

        return "shop/products/{$teamId}/{$directory}/";
    }
}
