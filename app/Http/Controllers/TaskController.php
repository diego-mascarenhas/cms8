<?php

namespace App\Http\Controllers;

use App\DataTables\TaskDataTable;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(TaskDataTable $dataTable)
    {
        return $dataTable->render('task.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'team_id' => 'required|exists:teams,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'status_id' => 'required|exists:task_statuses,id',
            'order' => 'nullable|integer',
        ]);

        Task::create($request->all());

        return redirect()->route('task.index')->with('success', 'Task created successfully');
    }

    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'status_id' => 'required|exists:task_statuses,id',
            'order' => 'nullable|integer',
        ]);

        $task->update($request->all());

        return redirect()->route('task.index')->with('success', 'Task updated successfully');
    }
} 