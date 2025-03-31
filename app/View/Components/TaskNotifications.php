<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Models\Task;

class TaskNotifications extends Component
{
    public $pendingTasks;
    
    public function __construct()
    {
        $this->pendingTasks = Task::pendingForUser(auth()->id())
            ->with(['status'])
            ->get();
    }

    public function render()
    {
        return view('components.task-notifications');
    }
} 