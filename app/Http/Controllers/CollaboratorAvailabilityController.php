<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactWeeklyAvailability;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CollaboratorAvailabilityController extends Controller
{
	/**
	 * Display collaborator availability view
	 */
	public function index($id)
	{
		// Get specified collaborator contact
		$contact = Contact::findOrFail($id);

		// Get collaborator absences for the next 6 months
		$startDate = Carbon::now()->startOfMonth();
		$endDate = Carbon::now()->addMonths(5)->endOfMonth();

		$absences = $contact->absences()
			->whereBetween('absence_date', [$startDate, $endDate])
			->get()
			->pluck('absence_date')
			->map(function ($date)
			{
				return $date->format('Y-m-d');
			})
			->toArray();

		// Get weekly availability
		$weeklyAvailability = $contact->weeklyAvailability;
		if (! $weeklyAvailability)
		{
			// Create default availability if none exists
			$weeklyAvailability = $contact->weeklyAvailability()->create([
				'contact_id' => $contact->id,
				'monday' => true,
				'tuesday' => true,
				'wednesday' => true,
				'thursday' => true,
				'friday' => true,
				'saturday' => false,
				'sunday' => false,
				'team_id' => auth()->user()->currentTeam->id,
			]);
		}

		// Generate months for calendar
		$months = [];
		for ($i = 0; $i < 6; $i++)
		{
			$currentMonth = Carbon::now()->addMonths($i);
			$months[] = [
				'name' => $currentMonth->format('F Y'),
				'month' => $currentMonth->format('n'),
				'year' => $currentMonth->format('Y'),
				'firstDay' => $currentMonth->copy()->startOfMonth()->dayOfWeek,
				'daysInMonth' => $currentMonth->daysInMonth,
				'startPadding' => $currentMonth->copy()->startOfMonth()->dayOfWeekIso - 1,
			];
		}

		return view('collaborator.absences', [
			'absences' => $absences,
			'weeklyAvailability' => $weeklyAvailability,
			'months' => $months,
			'collaborator' => $contact,
		]);
	}

	/**
	 * Toggle date availability
	 */
	public function toggleDate(Request $request, $id)
	{
		$request->validate([
			'date' => 'required|date',
		]);

		$contact = Contact::findOrFail($id);
		$date = Carbon::parse($request->date);

		// Check if absence already exists
		$absence = $contact->absences()->where('absence_date', $date)->first();

		if ($absence)
		{
			// Delete absence (mark as available)
			$absence->delete();

			return response()->json(['status' => 'available', 'date' => $request->date]);
		} else
		{
			// Create absence (mark as unavailable)
			$contact->absences()->create([
				'absence_date' => $date,
				'team_id' => auth()->user()->currentTeam->id,
			]);

			return response()->json(['status' => 'unavailable', 'date' => $request->date]);
		}
	}

	/**
	 * Update weekly availability
	 */
	public function updateWeekly(Request $request, $id)
	{
		$request->validate([
			'monday' => 'required|boolean',
			'tuesday' => 'required|boolean',
			'wednesday' => 'required|boolean',
			'thursday' => 'required|boolean',
			'friday' => 'required|boolean',
			'saturday' => 'required|boolean',
			'sunday' => 'required|boolean',
		]);

		$contact = Contact::findOrFail($id);

		// Get or create weekly availability
		$weeklyAvailability = $contact->weeklyAvailability;
		if (! $weeklyAvailability)
		{
			$weeklyAvailability = new ContactWeeklyAvailability;
			$weeklyAvailability->contact_id = $contact->id;
			$weeklyAvailability->team_id = auth()->user()->currentTeam->id;
		}

		// Update values
		$weeklyAvailability->monday = $request->monday;
		$weeklyAvailability->tuesday = $request->tuesday;
		$weeklyAvailability->wednesday = $request->wednesday;
		$weeklyAvailability->thursday = $request->thursday;
		$weeklyAvailability->friday = $request->friday;
		$weeklyAvailability->saturday = $request->saturday;
		$weeklyAvailability->sunday = $request->sunday;
		$weeklyAvailability->save();

		return response()->json([
			'status' => 'success',
			'data' => $weeklyAvailability,
		]);
	}
}
