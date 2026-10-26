<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMeetBookingRequest;
use App\Services\MeetBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MeetBookingController extends Controller
{
    public function slots(Request $request, MeetBookingService $booking): JsonResponse
    {
        $teamId = (int) $request->input('team_id');
        $month = (string) $request->query('month', '');

        if ($month !== '')
        {
            $validated = $request->validate([
                'month' => ['required', 'date_format:Y-m'],
            ]);
            $start = Carbon::parse($validated['month'].'-01');

            return response()->json([
                'month' => $start->format('Y-m'),
                'dates' => $booking->openDates($teamId, $start),
            ]);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $day = Carbon::parse($validated['date']);

        return response()->json([
            'date' => $day->toDateString(),
            'slots' => $booking->slots($teamId, $day),
        ]);
    }

    public function store(StoreMeetBookingRequest $request, MeetBookingService $booking): JsonResponse
    {
        $teamId = (int) $request->input('team_id');
        $booked = $booking->book(
            $teamId,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('start')->toString(),
        );

        return response()->json($booked, 201);
    }
}
