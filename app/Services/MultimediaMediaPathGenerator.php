<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Path generator for multimedia files
 *
 * File structure:
 * storage/app/public/multimedia/
 * ├── {team_hash}/
 * │   └── {multimedia_hash}/
 * │       ├── 2026/
 * │       │   ├── 01/
 * │       │   │   ├── file_timestamp.jpg
 * │       │   │   └── document_timestamp.pdf
 * │       ├── conversions/
 * │       └── responsive/
 */
class MultimediaMediaPathGenerator implements PathGenerator
{
    public function getTeamHash(int $teamId): string
    {
        return substr(md5('team_salt_'.$teamId.'_'.config('app.key')), 0, 12);
    }

    public function getMultimediaHash(int $multimediaId): string
    {
        return substr(md5('multimedia_salt_'.$multimediaId.'_'.config('app.key')), 0, 8);
    }

    public function getPath(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $multimediaId = $media->model->id;
        $teamHash = $this->getTeamHash($teamId);
        $multimediaHash = $this->getMultimediaHash($multimediaId);
        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "multimedia/{$teamHash}/{$multimediaHash}/{$year}/{$month}/";
    }

    public function getPathForConversions(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $multimediaId = $media->model->id;
        $teamHash = $this->getTeamHash($teamId);
        $multimediaHash = $this->getMultimediaHash($multimediaId);
        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "multimedia/{$teamHash}/{$multimediaHash}/conversions/{$year}/{$month}/";
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $multimediaId = $media->model->id;
        $teamHash = $this->getTeamHash($teamId);
        $multimediaHash = $this->getMultimediaHash($multimediaId);
        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "multimedia/{$teamHash}/{$multimediaHash}/responsive/{$year}/{$month}/";
    }
}
