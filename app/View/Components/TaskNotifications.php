<?php

namespace App\View\Components;

use App\Models\Task;
use Illuminate\View\Component;

class TaskNotifications extends Component
{
    public $pendingTasks;

    public function __construct()
    {
        $this->pendingTasks = Task::pendingForUser(auth()->id())
            ->with(['status', 'responsible'])
            ->get();
    }

    public function render()
    {
        return view('components.task-notifications');
    }
}
