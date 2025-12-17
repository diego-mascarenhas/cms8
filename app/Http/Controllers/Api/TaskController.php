<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
	/**
	 * Lista las tareas asignadas al usuario autenticado.
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index(Request $request)
	{
		$user = $request->user();

		// Query base: tareas asignadas al usuario
		$query = Task::where('responsible_id', $user->id)
			->with(['status', 'category', 'project', 'responsible']);

		// Filtros opcionales
		if ($request->has('status_id')) {
			$query->where('status_id', $request->status_id);
		}

		if ($request->has('pending_only') && $request->pending_only) {
			$query->whereHas('status', function ($q) {
				$q->whereNotIn('name', ['DONE', 'CANCELLED']);
			});
		}

		// Ordenamiento
		$tasks = $query->defaultOrder()->get();

		// Transformar a formato API
		$data = $tasks->map(function ($task) {
			return [
				'id' => $task->id,
				'title' => $task->title,
				'description' => $task->description,
				'start_date' => $task->start_date?->format('Y-m-d'),
				'due_date' => $task->due_date?->format('Y-m-d'),
				'estimated_hours' => $task->estimated_hours,
				'status' => [
					'id' => $task->status?->id,
					'name' => $task->status?->name,
					'translated_name' => $task->status?->translated_name,
				],
				'category' => [
					'id' => $task->category?->id,
					'name' => $task->category?->name,
				],
				'project' => $task->project ? [
					'id' => $task->project->id,
					'name' => $task->project->name,
				] : null,
				'responsible' => [
					'id' => $task->responsible?->id,
					'name' => $task->responsible?->name,
					'email' => $task->responsible?->email,
				],
			];
		});

		return response()->json([
			'success' => true,
			'data' => $data,
			'total' => $data->count(),
		]);
	}

	/**
	 * Muestra el detalle de una tarea específica.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function show(Request $request, $id)
	{
		$task = Task::with(['status', 'category', 'project', 'responsible'])
			->findOrFail($id);

		// Validar que el usuario tenga acceso a esta tarea
		// (El global scope ya filtra por team_id, pero verificamos responsible)
		if ($task->responsible_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
			return response()->json([
				'success' => false,
				'message' => __('No tienes permiso para ver esta tarea.'),
			], 403);
		}

		return response()->json([
			'success' => true,
			'data' => [
				'id' => $task->id,
				'title' => $task->title,
				'description' => $task->description,
				'start_date' => $task->start_date?->format('Y-m-d'),
				'due_date' => $task->due_date?->format('Y-m-d'),
				'estimated_hours' => $task->estimated_hours,
				'status' => [
					'id' => $task->status?->id,
					'name' => $task->status?->name,
					'translated_name' => $task->status?->translated_name,
				],
				'category' => [
					'id' => $task->category?->id,
					'name' => $task->category?->name,
				],
				'project' => $task->project ? [
					'id' => $task->project->id,
					'name' => $task->project->name,
				] : null,
				'responsible' => [
					'id' => $task->responsible?->id,
					'name' => $task->responsible?->name,
					'email' => $task->responsible?->email,
				],
				'board' => $task->board ? [
					'id' => $task->board->id,
					'name' => $task->board->name,
				] : null,
			],
		]);
	}
}
