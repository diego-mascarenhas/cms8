<?php

namespace App\Services;

use App\Models\Task;
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
        $task = $this->taskFor($media);

        $teamHash = $this->getTeamHash($task->team_id ?? 1);
        $taskHash = $this->getTaskHash($task->id);

        return "tasks/{$teamHash}/{$taskHash}/";
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        $task = $this->taskFor($media);

        $teamHash = $this->getTeamHash($task->team_id ?? 1);
        $taskHash = $this->getTaskHash($task->id);

        return "tasks/{$teamHash}/{$taskHash}/conversions/";
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        $task = $this->taskFor($media);

        $teamHash = $this->getTeamHash($task->team_id ?? 1);
        $taskHash = $this->getTaskHash($task->id);

        return "tasks/{$teamHash}/{$taskHash}/responsive/";
    }

    /**
     * The team scope hides a task from a client whose current team is not the project's.
     * Media still belongs to that task, so the path can be built from the unscoped record.
     */
    private function taskFor(Media $media): Task
    {
        $model = $media->model;

        if (! $model instanceof Task)
        {
            $model = Task::withoutGlobalScopes()->find($media->model_id);
            if ($model)
            {
                $media->setRelation('model', $model);
            }
        }

        if (! $model instanceof Task)
        {
            throw new \Exception('Media model is null - cannot generate path');
        }

        return $model;
    }
}
