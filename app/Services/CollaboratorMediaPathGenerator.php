<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Illuminate\Support\Str;

/**
 * Path generator for collaborator media
 *
 * Implements organized and secure hierarchy similar to GrapeJS:
 *
 * File structure:
 * storage/app/public/media/
 * ├── {team_hash}/                    # Team hash (12 characters)
 * │   └── {collaborator_hash}/        # Collaborator hash (8 characters)
 * │       ├── 2025/                   # Upload year
 * │       │   ├── 01/                 # Upload month
 * │       │   │   ├── file1_timestamp.jpg
 * │       │   │   └── document_timestamp.pdf
 * │       │   └── 02/
 * │       │       └── video_timestamp.mp4
 * │       ├── conversions/            # Image conversions
 * │       └── responsive/             # Responsive images
 */
class CollaboratorMediaPathGenerator implements PathGenerator
{
    /**
     * Generate a secure hash for the team ID
     */
    public function getTeamHash($teamId)
    {
        return substr(md5('team_salt_'.$teamId.'_'.config('app.key')), 0, 12);
    }

    /**
     * Generate a secure hash for the collaborator ID
     */
    public function getCollaboratorHash($collaboratorId)
    {
        return substr(md5('collaborator_salt_'.$collaboratorId.'_'.config('app.key')), 0, 8);
    }

    /**
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        if (!$media->model) {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $collaboratorId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $collaboratorHash = $this->getCollaboratorHash($collaboratorId);

        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "media/{$teamHash}/{$collaboratorHash}/{$year}/{$month}/";
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        if (!$media->model) {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $collaboratorId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $collaboratorHash = $this->getCollaboratorHash($collaboratorId);
        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "media/{$teamHash}/{$collaboratorHash}/conversions/{$year}/{$month}/";
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        if (!$media->model) {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $collaboratorId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $collaboratorHash = $this->getCollaboratorHash($collaboratorId);
        $year = $media->created_at->format('Y');
        $month = $media->created_at->format('m');

        return "media/{$teamHash}/{$collaboratorHash}/responsive/{$year}/{$month}/";
    }
}
