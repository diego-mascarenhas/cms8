<?php

namespace App\View\Components;

use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class TaskNotifications extends Component
{
    public Collection $notifications;

    public int $unreadCount;

    public function __construct()
    {
        $userId = auth()->id();

        $this->notifications = $userId
            ? Notification::query()
                ->forRecipientUser($userId)
                ->with(['type', 'contact'])
                ->latest()
                ->limit(15)
                ->get()
            : collect();

        $this->unreadCount = $userId
            ? Notification::query()->forRecipientUser($userId)->unread()->count()
            : 0;
    }

    public function render()
    {
        return view('components.task-notifications');
    }
}
