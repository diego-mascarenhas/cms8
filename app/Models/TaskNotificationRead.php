<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskNotificationRead extends Model
{
    protected $table = 'task_notification_reads';

    public $timestamps = false;

    protected $fillable = ['task_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the given task IDs as read for the given user (or all pending tasks if empty).
     */
    public static function markAsReadForUser(int $userId, array $taskIds = []): void
    {
        $query = Task::withoutGlobalScopes()->pendingForUser($userId);
        if (auth()->check() && auth()->user()->currentTeam)
        {
            $query->where('tasks.team_id', auth()->user()->currentTeam->id);
        }
        if ($taskIds !== [])
        {
            $query->whereIn('tasks.id', $taskIds);
        }
        $ids = $query->pluck('id');
        $now = now();
        foreach ($ids as $taskId)
        {
            self::query()->updateOrInsert(
                ['task_id' => $taskId, 'user_id' => $userId],
                ['read_at' => $now],
            );
        }
    }
}
