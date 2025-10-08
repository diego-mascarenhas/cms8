<?php

namespace App\Http\Controllers;

use App\DataTables\TimeDataTable;
use App\Models\Project;
use App\Models\Task;
use App\Models\Time;
use App\Models\User;
use Illuminate\Http\Request;

class TimeController extends Controller
{
	public function index(TimeDataTable $dataTable)
	{
		$runningTimer = Time::getRunningTimer();

		return $dataTable->render('time.index', compact('runningTimer'));
	}

	public function create()
	{
		$projects = Project::select('id', 'name')
			->whereIn('status_id', [7, 8, 9]) // Active/In-progress statuses
			->orderBy('name')
			->get();
		$tasks = Task::select('id', 'title')->orderBy('title')->get();
		$users = auth()->user()->currentTeam->allUsers();

		return view('time.form', compact('projects', 'tasks', 'users'));
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'project_id' => 'nullable|exists:projects,id',
			'task_id' => 'nullable|exists:tasks,id',
			'description' => 'nullable|string|max:255',
			'start_time' => 'required|date',
			'end_time' => 'nullable|date|after:start_time',
			'is_billable' => 'boolean',
			'hourly_rate' => 'nullable|numeric|min:0',
		]);

		$validated['team_id'] = auth()->user()->currentTeam->id;
		$validated['user_id'] = auth()->id();

		$time = Time::create($validated);

		if ($time->end_time) {
			$time->calculateDuration();
		}

		return redirect()
			->route('time.index')
			->with('success', __('Time entry created successfully.'));
	}

	public function edit($id)
	{
		$data = Time::findOrFail($id);

		// Only allow editing own time entries or if admin
		if ($data->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
			abort(403, 'Unauthorized action.');
		}

		$projects = Project::select('id', 'name')
			->whereIn('status_id', [7, 8, 9]) // Active/In-progress statuses
			->orderBy('name')
			->get();
		$tasks = Task::select('id', 'title')->orderBy('title')->get();
		$users = auth()->user()->currentTeam->allUsers();

		return view('time.form', compact('data', 'projects', 'tasks', 'users'));
	}

	public function update(Request $request, $id)
	{
		$time = Time::findOrFail($id);

		// Only allow editing own time entries or if admin
		if ($time->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
			abort(403, 'Unauthorized action.');
		}

		$validated = $request->validate([
			'project_id' => 'nullable|exists:projects,id',
			'task_id' => 'nullable|exists:tasks,id',
			'description' => 'nullable|string|max:255',
			'start_time' => 'required|date',
			'end_time' => 'nullable|date|after:start_time',
			'is_billable' => 'boolean',
			'hourly_rate' => 'nullable|numeric|min:0',
		]);

		$time->update($validated);

		if ($time->end_time) {
			$time->calculateDuration();
		}

		return redirect()
			->route('time.index')
			->with('success', __('Time entry updated successfully.'));
	}

	public function destroy($id)
	{
		$time = Time::findOrFail($id);

		// Only allow deleting own time entries or if admin
		if ($time->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
			abort(403, 'Unauthorized action.');
		}

		$time->delete();

		return redirect()
			->route('time.index')
			->with('success', __('Time entry deleted successfully.'));
	}

	/**
	 * Start a new timer
	 */
	public function start(Request $request)
	{
		// Check if user already has a running timer
		$runningTimer = Time::getRunningTimer();

		if ($runningTimer) {
			return response()->json([
				'success' => false,
				'message' => __('You already have a running timer. Please stop it first.'),
			], 400);
		}

		$validated = $request->validate([
			'project_id' => 'nullable|exists:projects,id',
			'task_id' => 'nullable|exists:tasks,id',
			'description' => 'nullable|string|max:255',
		]);

		$isAttendance = empty($validated['project_id'] ?? null) && empty($validated['task_id'] ?? null);

		$time = Time::create([
			'team_id' => auth()->user()->currentTeam->id,
			'user_id' => auth()->id(),
			'project_id' => $validated['project_id'] ?? null,
			'task_id' => $validated['task_id'] ?? null,
			'description' => $validated['description'] ?? null,
			'start_time' => now(),
			'is_billable' => $isAttendance ? false : true,
		]);

		return response()->json([
			'success' => true,
			'message' => __('Timer started successfully.'),
			'time' => $time,
		]);
	}

	/**
	 * Stop the running timer
	 */
	public function stop($id)
	{
		$time = Time::findOrFail($id);

		// Only allow stopping own timer
		if ($time->user_id !== auth()->id()) {
			return response()->json([
				'success' => false,
				'message' => __('Unauthorized action.'),
			], 403);
		}

		if (!$time->isRunning()) {
			return response()->json([
				'success' => false,
				'message' => __('Timer is not running.'),
			], 400);
		}

		$time->stop();

		return response()->json([
			'success' => true,
			'message' => __('Timer stopped successfully.'),
			'time' => $time->fresh(),
			'duration' => $time->formatted_duration,
		]);
	}

	/**
	 * Get currently running timer
	 */
	public function running()
	{
		$runningTimer = Time::getRunningTimer();

		return response()->json([
			'running' => $runningTimer ? true : false,
			'time' => $runningTimer,
		]);
	}
}
