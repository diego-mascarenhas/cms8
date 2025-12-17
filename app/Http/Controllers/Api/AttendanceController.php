<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
	/**
	 * Get attendance history for the authenticated user.
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index(Request $request)
	{
		$user = $request->user();

		// Base query: user's attendances
		$query = Attendance::where('user_id', $user->id);

		// Optional filters
		if ($request->has('date_from'))
		{
			$query->whereDate('start_at', '>=', $request->date_from);
		}

		if ($request->has('date_to'))
		{
			$query->whereDate('start_at', '<=', $request->date_to);
		}

		// Only completed attendances by default
		if (! $request->has('include_running') || ! $request->include_running)
		{
			$query->whereNotNull('end_at');
		}

		// Order by most recent first
		$attendances = $query->orderBy('start_at', 'desc')
			->limit($request->input('limit', 50))
			->get();

		// Transform to API format
		$data = $attendances->map(function ($attendance)
		{
			return [
				'id' => $attendance->id,
				'start_at' => $attendance->start_at?->toISOString(),
				'end_at' => $attendance->end_at?->toISOString(),
				'duration_seconds' => $attendance->duration_seconds,
				'duration_formatted' => $this->formatDuration($attendance->duration_seconds),
				'duration_hours' => $attendance->duration_seconds ? round($attendance->duration_seconds / 3600, 2) : 0,
				'is_running' => $attendance->isRunning(),
				'is_paused' => (bool) $attendance->paused_at,
				'paused_seconds' => $attendance->paused_seconds,
			];
		});

		return response()->json([
			'success' => true,
			'data' => $data,
			'total' => $data->count(),
		]);
	}

	/**
	 * Get currently running attendance.
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function running(Request $request)
	{
		$attendance = Attendance::getRunning($request->user()->id);

		if ($attendance)
		{
			$elapsedSeconds = now()->diffInSeconds($attendance->start_at);
			$pausedSeconds = (int) ($attendance->paused_seconds ?? 0);
			
			// If currently paused, add current pause duration
			if ($attendance->paused_at)
			{
				$pausedSeconds += now()->diffInSeconds($attendance->paused_at);
			}

			$workingSeconds = max(0, $elapsedSeconds - $pausedSeconds);

			return response()->json([
				'success' => true,
				'running' => true,
				'data' => [
					'id' => $attendance->id,
					'start_at' => $attendance->start_at?->toISOString(),
					'elapsed_seconds' => $elapsedSeconds,
					'working_seconds' => $workingSeconds,
					'paused_seconds' => $pausedSeconds,
					'is_paused' => (bool) $attendance->paused_at,
					'paused_at' => $attendance->paused_at?->toISOString(),
				],
			]);
		}

		return response()->json([
			'success' => true,
			'running' => false,
			'data' => null,
		]);
	}

	/**
	 * Clock in (start attendance).
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function clockIn(Request $request)
	{
		// Check if already clocked in
		$runningAttendance = Attendance::getRunning($request->user()->id);
		if ($runningAttendance)
		{
			return response()->json([
				'success' => false,
				'message' => __('Ya tienes una jornada activa.'),
				'data' => [
					'id' => $runningAttendance->id,
					'start_at' => $runningAttendance->start_at?->toISOString(),
					'elapsed_seconds' => now()->diffInSeconds($runningAttendance->start_at),
				],
			], 400);
		}

		// Create new attendance
		$attendance = Attendance::create([
			'team_id' => $request->user()->currentTeam->id,
			'user_id' => $request->user()->id,
			'start_at' => now(),
		]);

		return response()->json([
			'success' => true,
			'message' => __('Jornada iniciada correctamente.'),
			'data' => [
				'id' => $attendance->id,
				'start_at' => $attendance->start_at?->toISOString(),
			],
		], 201);
	}

	/**
	 * Clock out (end attendance).
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function clockOut(Request $request, $id)
	{
		$attendance = Attendance::findOrFail($id);

		// Validate user owns this attendance
		if ($attendance->user_id !== $request->user()->id)
		{
			return response()->json([
				'success' => false,
				'message' => __('No tienes permiso para finalizar esta jornada.'),
			], 403);
		}

		// Check if attendance is running
		if (! $attendance->isRunning())
		{
			return response()->json([
				'success' => false,
				'message' => __('Esta jornada ya está finalizada.'),
			], 400);
		}

		// If paused, resume first
		if ($attendance->paused_at)
		{
			$attendance->resume();
		}

		// Stop attendance
		$attendance->stop();

		return response()->json([
			'success' => true,
			'message' => __('Jornada finalizada correctamente.'),
			'data' => [
				'id' => $attendance->id,
				'start_at' => $attendance->start_at?->toISOString(),
				'end_at' => $attendance->end_at?->toISOString(),
				'duration_seconds' => $attendance->duration_seconds,
				'duration_formatted' => $this->formatDuration($attendance->duration_seconds),
				'duration_hours' => round($attendance->duration_seconds / 3600, 2),
				'paused_seconds' => $attendance->paused_seconds,
			],
		]);
	}

	/**
	 * Pause attendance.
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function pause(Request $request, $id)
	{
		$attendance = Attendance::findOrFail($id);

		// Validate user owns this attendance
		if ($attendance->user_id !== $request->user()->id)
		{
			return response()->json([
				'success' => false,
				'message' => __('No tienes permiso para pausar esta jornada.'),
			], 403);
		}

		// Check if attendance is running
		if (! $attendance->isRunning())
		{
			return response()->json([
				'success' => false,
				'message' => __('Esta jornada no está activa.'),
			], 400);
		}

		// Check if already paused
		if ($attendance->paused_at)
		{
			return response()->json([
				'success' => false,
				'message' => __('La jornada ya está pausada.'),
			], 400);
		}

		// Pause attendance
		$attendance->pause();

		return response()->json([
			'success' => true,
			'message' => __('Jornada pausada correctamente.'),
			'data' => [
				'id' => $attendance->id,
				'paused_at' => $attendance->paused_at?->toISOString(),
			],
		]);
	}

	/**
	 * Resume attendance.
	 *
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function resume(Request $request, $id)
	{
		$attendance = Attendance::findOrFail($id);

		// Validate user owns this attendance
		if ($attendance->user_id !== $request->user()->id)
		{
			return response()->json([
				'success' => false,
				'message' => __('No tienes permiso para reanudar esta jornada.'),
			], 403);
		}

		// Check if attendance is running
		if (! $attendance->isRunning())
		{
			return response()->json([
				'success' => false,
				'message' => __('Esta jornada no está activa.'),
			], 400);
		}

		// Check if paused
		if (! $attendance->paused_at)
		{
			return response()->json([
				'success' => false,
				'message' => __('La jornada no está pausada.'),
			], 400);
		}

		// Resume attendance
		$attendance->resume();

		return response()->json([
			'success' => true,
			'message' => __('Jornada reanudada correctamente.'),
			'data' => [
				'id' => $attendance->id,
				'paused_seconds' => $attendance->paused_seconds,
			],
		]);
	}

	/**
	 * Format duration in human-readable format.
	 *
	 * @param  int|null  $seconds
	 * @return string
	 */
	private function formatDuration($seconds)
	{
		if (! $seconds)
		{
			return '0m';
		}

		$hours = floor($seconds / 3600);
		$minutes = floor(($seconds % 3600) / 60);

		if ($hours > 0)
		{
			return sprintf('%dh %dm', $hours, $minutes);
		}

		return sprintf('%dm', $minutes);
	}
}

