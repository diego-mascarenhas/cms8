<?php

namespace App\Http\Controllers;

use App\DataTables\TaskDataTable;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Category;
use App\Models\TaskBoard;
use App\Models\TaskStatus;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(TaskDataTable $dataTable, Request $request)
    {
        if ($request->has('view') && $request->view === 'kanban')
        {
            return $this->kanban($request);
        }

        return $dataTable->render('task.index');
    }

    public function kanban(Request $request)
    {
        $board = null;
        $project = null;

        // Check if we have a project_id parameter
        if ($request->has('project_id'))
        {
            $project = Project::findOrFail($request->project_id);

            // Use project's board if it exists, otherwise create one
            if ($project->board_id)
            {
                $board = TaskBoard::find($project->board_id);
            } else
            {
                // Create board for project if it doesn't exist
                $board = TaskBoard::create([
                    'team_id' => auth()->user()->currentTeam->id,
                    'name' => "Project: {$project->name}",
                    'description' => "Task board for project: {$project->name}",
                    'is_default' => false,
                    'order' => 0,
                ]);

                $project->update(['board_id' => $board->id]);
            }
        } else
        {
            // Original single-board kanban: always use default board
            $board = TaskBoard::getDefaultBoard();
        }

        $statuses = TaskStatus::orderBy('order')->get()->map(function ($status)
        {
            return [
                'id' => $status->id,
                'name' => $status->translated_name,
                'original_name' => $status->name,
            ];
        });

        // Get tasks grouped by status
        $tasksByStatus = [];
        foreach ($statuses as $status)
        {
            $tasks = Task::where('status_id', $status['id'])
                ->where('board_id', $board->id)
                ->with(['responsible', 'category'])
                ->orderBy('order')
                ->get();

            $tasksByStatus[$status['id']] = $tasks->map(function ($task)
            {
                $attachment = $task->getFirstMediaUrl('attachments');
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                    'estimated_hours' => $task->estimated_hours,
                    'attachment' => $attachment ?: null,
                    'responsible' => $task->responsible ? [
                        'id' => $task->responsible->id,
                        'name' => $task->responsible->name,
                    ] : null,
                    'category' => $task->category ? [
                        'id' => $task->category->id,
                        'name' => $task->category->name,
                    ] : null,
                ];
            });
        }

        // Options for offcanvas editing - Only admin and collaborators
        $users = User::query()
            ->whereHas('teams', function ($q) {
                $q->where('team_id', auth()->user()->currentTeam->id);
            })
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['admin', 'collaborator']);
            })
            ->get(['id', 'name'])
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);

        $categories = class_exists(Category::class)
            ? Category::query()->get(['id', 'name'])->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            : collect();

        return view('task.kanban', compact('statuses', 'tasksByStatus', 'project', 'board', 'users', 'categories'));
    }

    public function create(Request $request)
    {
        $statuses = TaskStatus::getOptions();
        $boards = TaskBoard::getOptions();
        $defaultBoardId = null;
        $projectId = $request->input('project_id');
        $defaultStatusId = $request->integer('status_id') ?: 1;

        // If coming from kanban view, get the board_id
        if ($request->has('board_id'))
        {
            $defaultBoardId = $request->input('board_id');
        } else
        {
            $defaultBoard = TaskBoard::getDefaultBoard();
            $defaultBoardId = $defaultBoard->id;
        }

        return view('task.form', compact('statuses', 'boards', 'defaultBoardId', 'projectId', 'defaultStatusId'));
    }

    public function store(Request $request)
    {
        $data = $request->except(['id', '_token', 'attachment']);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsible_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'status_id' => 'required|exists:task_statuses,id',
            'category_id' => 'nullable|exists:categories,id',
            'board_id' => 'nullable|exists:task_boards,id',
            'estimated_hours' => 'nullable|numeric|min:0',
            'attachment' => 'nullable|file|image|max:10240', // Max 10MB
        ]);

        $boardId = $data['board_id'] ?? null;
        if (! $boardId)
        {
            // Get default board if none provided
            $board = TaskBoard::getDefaultBoard();
            $boardId = $board->id;
        }

        $task = Task::updateOrCreate(
            ['id' => $request->id],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'category_id' => $data['category_id'] ?? null,
                'responsible_id' => $data['responsible_id'],
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'order' => 0,
                'status_id' => $data['status_id'] ?? 1,
                'board_id' => $boardId,
                'team_id' => auth()->user()->currentTeam->id,
            ],
        );

        // Handle file upload using Spatie Media Library
        \Log::info('TaskController Store - Request has file?', [
            'hasFile' => $request->hasFile('attachment'),
            'allFiles' => $request->allFiles(),
            'all' => $request->all()
        ]);

        if ($request->hasFile('attachment'))
        {
            \Log::info('Processing attachment upload', [
                'file' => $request->file('attachment')->getClientOriginalName()
            ]);

            // Delete old attachments first (safely)
            try {
                $existingMedia = $task->getMedia('attachments');
                foreach ($existingMedia as $media) {
                    $media->delete();
                }
            } catch (\Exception $e) {
                // Ignore errors when deleting old files
                \Log::warning('Could not delete old attachment: ' . $e->getMessage());
            }

            // Add new attachment
            try {
                $task->addMediaFromRequest('attachment')->toMediaCollection('attachments');
                \Log::info('Attachment uploaded successfully');
            } catch (\Exception $e) {
                \Log::error('Error uploading attachment: ' . $e->getMessage());
            }
        }

        if ($request->expectsJson())
        {
            // Refresh task to get latest media
            $task->refresh();
            $attachmentUrl = $task->getFirstMediaUrl('attachments');

            // If no URL, try to get the full media object
            if (empty($attachmentUrl)) {
                $media = $task->getFirstMedia('attachments');
                if ($media) {
                    $attachmentUrl = $media->getUrl();
                }
            }

            \Log::info('Returning attachment URL', [
                'attachmentUrl' => $attachmentUrl,
                'task_id' => $task->id
            ]);

            return response()->json([
                'success' => true,
                'id' => $task->id,
                'attachment' => $attachmentUrl ?: null
            ]);
        }

        if ($request->has('view') && $request->view === 'kanban')
        {
            // Check if we have a project_id to redirect back to project kanban
            $projectId = $request->input('project_id');
            if ($projectId)
            {
                return redirect()->route('task.index', ['view' => 'kanban', 'project_id' => $projectId])->with('success', 'Record saved successfully.');
            }
            return redirect()->route('task.index', ['view' => 'kanban', 'board_id' => $boardId])->with('success', 'Record saved successfully.');
        }

        return redirect()->route('task.index')->with('success', 'Record saved successfully.');
    }

    public function show(string $id)
    {
        $task = Task::with(['responsible', 'status', 'category'])
            ->findOrFail($id);

        return view('task.show', compact('task'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Task::findOrFail($id);
        $statuses = TaskStatus::getOptions();
        $boards = TaskBoard::getOptions();
        $defaultBoardId = $data->board_id;

        return view('task.form', compact('data', 'statuses', 'boards', 'defaultBoardId'));
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'status_id' => 'required|exists:task_statuses,id',
            'order' => 'required|integer|min:0',
        ]);

        $task = Task::findOrFail($request->task_id);
        $task->status_id = $request->status_id;
        $task->order = $request->order;
        $task->save();

        return response()->json(['success' => true]);
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->tasks as $taskData)
        {
            $task = Task::findOrFail($taskData['id']);
            $task->order = $taskData['order'];
            $task->save();
        }

        return response()->json(['success' => true]);
    }

    public function destroy(string $id)
    {
        try
        {
            $task = Task::findOrFail($id);
            $task->delete();

            return response()->json(['success' => true, 'message' => 'Task deleted successfully']);
        } catch (\Exception $e)
        {
            return response()->json(['success' => false, 'message' => 'Error deleting task'], 500);
        }
    }
}
