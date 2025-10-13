<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function start(Request $request)
    {
        $running = Attendance::getRunning();
        if ($running)
        {
            return response()->json([
                'success' => false,
                'message' => __('You already have an active attendance.'),
            ], 400);
        }

        $attendance = Attendance::create([
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
            'start_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance,
        ]);
    }

    public function stop($id)
    {
        $attendance = Attendance::findOrFail($id);

        if ($attendance->user_id !== auth()->id())
        {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action.'),
            ], 403);
        }

        if (! $attendance->isRunning())
        {
            return response()->json([
                'success' => false,
                'message' => __('Attendance is not running.'),
            ], 400);
        }

        $attendance->stop();

        return response()->json([
            'success' => true,
            'attendance' => $attendance->fresh(),
            'duration' => $attendance->duration_seconds,
        ]);
    }

    public function pause($id)
    {
        $attendance = Attendance::findOrFail($id);

        if ($attendance->user_id !== auth()->id())
        {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action.'),
            ], 403);
        }

        if (! $attendance->pause())
        {
            return response()->json([
                'success' => false,
                'message' => __('Unable to pause.'),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'attendance' => $attendance->fresh(),
        ]);
    }

    public function resume($id)
    {
        $attendance = Attendance::findOrFail($id);

        if ($attendance->user_id !== auth()->id())
        {
            return response()->json([
                'success' => false,
                'message' => __('Unauthorized action.'),
            ], 403);
        }

        if (! $attendance->resume())
        {
            return response()->json([
                'success' => false,
                'message' => __('Unable to resume.'),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'attendance' => $attendance->fresh(),
        ]);
    }

    public function running()
    {
        $running = Attendance::getRunning();

        return response()->json([
            'running' => $running ? true : false,
            'attendance' => $running,
        ]);
    }
}
