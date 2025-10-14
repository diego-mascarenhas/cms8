<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Path generator for task media
 *
 * Implements organized and secure hierarchy:
 *
 * File structure:
 * storage/app/public/tasks/
 * ├── {team_hash}/					# Team hash (12 characters)
 * │   └── {task_hash}/				# Task hash (8 characters)
 * │       ├── file1_timestamp.jpg
 * │       ├── document_timestamp.pdf
 * │       ├── conversions/			# Image conversions
 * │       └── responsive/			# Responsive images
 */
class TaskMediaPathGenerator implements PathGenerator
{
    /**
     * Generate a secure hash for the team ID
     */
    public function getTeamHash($teamId)
    {
        return substr(md5('team_salt_'.$teamId.'_'.config('app.key')), 0, 12);
    }

    /**
     * Generate a secure hash for the task ID
     */
    public function getTaskHash($taskId)
    {
        return substr(md5('task_salt_'.$taskId.'_'.config('app.key')), 0, 8);
    }

    /**
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $taskId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $taskHash = $this->getTaskHash($taskId);

        return "tasks/{$teamHash}/{$taskHash}/";
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $taskId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $taskHash = $this->getTaskHash($taskId);

        return "tasks/{$teamHash}/{$taskHash}/conversions/";
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        if (! $media->model)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        $teamId = $media->model->team_id ?? 1;
        $taskId = $media->model->id;

        $teamHash = $this->getTeamHash($teamId);
        $taskHash = $this->getTaskHash($taskId);

        return "tasks/{$teamHash}/{$taskHash}/responsive/";
    }
}
