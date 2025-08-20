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

	public function create()
	{
		$statuses = TaskStatus::getOptions();

		return view('task.form', compact('statuses'));
	}

	public function store(Request $request)
	{
		$data = $request->except(['id', '_token']);

		$request->validate([
			'title' => 'required|string|max:255',
			'description' => 'required|string',
			'responsible_id' => 'required|exists:users,id',
			'start_date' => 'required|date',
			'due_date' => 'required|date|after_or_equal:start_date',
			'status_id' => 'required|exists:task_statuses,id',
			'category_id' => 'nullable|exists:categories,id',
		]);

		Task::updateOrCreate(
			['id' => $request->id],
			[
				'title' => $data['title'],
				'description' => $data['description'],
				'category_id' => $data['category_id'] ?? null,
				'responsible_id' => $data['responsible_id'],
				'start_date' => $data['start_date'] ?? null,
				'due_date' => $data['due_date'] ?? null,
				'order' => 0,
				'status_id' => $data['status_id'] ?? 1,
				'team_id' => auth()->user()->currentTeam->id,
			],
		);

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

		return view('task.form', compact('data', 'statuses'));
	}
}
