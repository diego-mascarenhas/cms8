<?php

namespace App\Http\Controllers;

use App\DataTables\EmployeeDataTable;
use App\Models\Contact;
use App\Models\ContactAbsence;
use App\Models\ContactWeeklyAvailability;
use App\Models\ContactStatus;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;

class EmployeeController extends Controller
{
	public function index(EmployeeDataTable $dataTable)
	{
		// Get employee statistics
		$dashboardStats = [
			'totalEmployees' => Contact::whereHas('user', function ($query) {
				$query->whereHas('roles', function ($q) {
					$q->where('name', 'employee');
				});
			})->count(),
			'activeEmployees' => Contact::whereHas('user', function ($query) {
				$query->whereHas('roles', function ($q) {
					$q->where('name', 'employee');
				});
			})->whereRaw("JSON_EXTRACT(data, '$.active') = true")->count(),
			'newThisWeek' => Contact::whereHas('user', function ($query) {
				$query->whereHas('roles', function ($q) {
					$q->where('name', 'employee');
				});
			})->where('created_at', '>=', now()->subWeek())->count(),
		];

		return $dataTable->render('employee.index', compact('dashboardStats'));
	}

	public function create()
	{
		$statuses = ContactStatus::all();
		$languages = Language::all();
		$users = User::whereHas('roles', function ($query) {
			$query->where('name', 'employee');
		})->get();

		return view('employee.form', compact('statuses', 'languages', 'users'));
	}

	public function store(Request $request)
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'surname' => 'nullable|string|max:255',
			'email' => 'required|email|max:255',
			'phone' => 'nullable|string|max:20',
			'birthday' => 'nullable|date',
			'language' => 'required|string|exists:languages,code',
			'profile' => 'nullable|string',
			'responsible_id' => 'nullable|exists:users,id',
			'status_id' => 'required|exists:contact_statuses,id',
			// Additional fields for employees
			'city' => 'nullable|string|max:255',
			'province' => 'nullable|string|max:255',
			'command' => 'nullable|string|max:50',
			'active' => 'boolean',
			'dni' => 'nullable|string|max:20',
			'nationality' => 'nullable|string|max:100',
			'naf' => 'nullable|string|max:50',
			'address' => 'nullable|string|max:500',
			'postal_code' => 'nullable|string|max:10',
			'contract_type' => 'nullable|string|max:100',
			'account_number' => 'nullable|string|max:50',
		]);

		// Add creator_id and team_id automatically
		$validated['creator_id'] = auth()->user()->id;
		$validated['team_id'] = auth()->user()->currentTeam->id;

		// Prepare data JSON field
		$data = [
			'city' => $validated['city'] ?? null,
			'province' => $validated['province'] ?? null,
			'command' => $validated['command'] ?? null,
			'active' => $validated['active'] ?? true,
			'dni' => $validated['dni'] ?? null,
			'nationality' => $validated['nationality'] ?? null,
			'naf' => $validated['naf'] ?? null,
			'address' => $validated['address'] ?? null,
			'postal_code' => $validated['postal_code'] ?? null,
			'contract_type' => $validated['contract_type'] ?? null,
			'account_number' => $validated['account_number'] ?? null,
		];

		$contact = Contact::create([
			'name' => $validated['name'],
			'surname' => $validated['surname'] ?? null,
			'email' => $validated['email'],
			'phone' => $validated['phone'] ?? null,
			'birthday' => $validated['birthday'] ?? null,
			'language' => $validated['language'],
			'profile' => $validated['profile'] ?? null,
			'responsible_id' => $validated['responsible_id'] ?? null,
			'status_id' => $validated['status_id'],
			'creator_id' => $validated['creator_id'],
			'team_id' => $validated['team_id'],
			'data' => $data,
		]);

		// Create weekly availability record
		$contact->weeklyAvailability()->create([
			'monday' => true,
			'tuesday' => true,
			'wednesday' => true,
			'thursday' => true,
			'friday' => true,
			'saturday' => false,
			'sunday' => false,
		]);

		return redirect()->route('employee.show', $contact->id)
			->with('success', 'Employee created successfully.');
	}

	public function show($id)
	{
		$contact = Contact::with([
			'status',
			'responsible',
			'creator',
			'language',
			'absences',
			'weeklyAvailability',
		])->findOrFail($id);

		// Calculate total time (placeholder - you can implement actual time tracking)
		$totalSeconds = 0;

		return view('employee.show', compact('contact', 'totalSeconds'));
	}

	public function edit($id)
	{
		$contact = Contact::findOrFail($id);
		$statuses = ContactStatus::all();
		$languages = Language::all();
		$users = User::whereHas('roles', function ($query) {
			$query->where('name', 'employee');
		})->get();

		return view('employee.form', compact('contact', 'statuses', 'languages', 'users'));
	}

	public function update(Request $request, $id)
	{
		$contact = Contact::findOrFail($id);

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'surname' => 'nullable|string|max:255',
			'email' => 'required|email|max:255',
			'phone' => 'nullable|string|max:20',
			'birthday' => 'nullable|date',
			'language' => 'required|string|exists:languages,code',
			'profile' => 'nullable|string',
			'responsible_id' => 'nullable|exists:users,id',
			'status_id' => 'required|exists:contact_statuses,id',
			// Additional fields for employees
			'city' => 'nullable|string|max:255',
			'province' => 'nullable|string|max:255',
			'command' => 'nullable|string|max:50',
			'active' => 'boolean',
			'dni' => 'nullable|string|max:20',
			'nationality' => 'nullable|string|max:100',
			'naf' => 'nullable|string|max:50',
			'address' => 'nullable|string|max:500',
			'postal_code' => 'nullable|string|max:10',
			'contract_type' => 'nullable|string|max:100',
			'account_number' => 'nullable|string|max:50',
		]);

		// Prepare data JSON field
		$data = $contact->data ?? (object)[];
		$data->city = $validated['city'] ?? null;
		$data->province = $validated['province'] ?? null;
		$data->command = $validated['command'] ?? null;
		$data->active = $validated['active'] ?? true;
		$data->dni = $validated['dni'] ?? null;
		$data->nationality = $validated['nationality'] ?? null;
		$data->naf = $validated['naf'] ?? null;
		$data->address = $validated['address'] ?? null;
		$data->postal_code = $validated['postal_code'] ?? null;
		$data->contract_type = $validated['contract_type'] ?? null;
		$data->account_number = $validated['account_number'] ?? null;

		$contact->update([
			'name' => $validated['name'],
			'surname' => $validated['surname'] ?? null,
			'email' => $validated['email'],
			'phone' => $validated['phone'] ?? null,
			'birthday' => $validated['birthday'] ?? null,
			'language' => $validated['language'],
			'profile' => $validated['profile'] ?? null,
			'responsible_id' => $validated['responsible_id'] ?? null,
			'status_id' => $validated['status_id'],
			'data' => $data,
		]);

		return redirect()->route('employee.show', $contact->id)
			->with('success', 'Employee updated successfully.');
	}

	public function destroy($id)
	{
		$contact = Contact::findOrFail($id);
		$contact->delete();

		return redirect()->route('employee.index')
			->with('success', 'Employee deleted successfully.');
	}

	public function absences($id)
	{
		$contact = Contact::with(['absences', 'weeklyAvailability'])->findOrFail($id);

		// Get absences for the current year
		$absences = $contact->absences()
			->whereYear('date', date('Y'))
			->pluck('date')
			->toArray();

		// Get weekly availability
		$weeklyAvailability = $contact->weeklyAvailability;
		if (!$weeklyAvailability) {
			$weeklyAvailability = $contact->weeklyAvailability()->create([
				'monday' => true,
				'tuesday' => true,
				'wednesday' => true,
				'thursday' => true,
				'friday' => true,
				'saturday' => false,
				'sunday' => false,
			]);
		}

		// Generate months data for the calendar
		$months = [];
		$currentYear = date('Y');

		for ($month = 1; $month <= 12; $month++) {
			$date = Carbon::createFromDate($currentYear, $month, 1);
			$daysInMonth = $date->daysInMonth;
			$startPadding = $date->copy()->startOfMonth()->dayOfWeek;

			// Adjust for Monday as first day of week
			$startPadding = $startPadding == 0 ? 6 : $startPadding - 1;

			$months[] = [
				'name' => $date->format('F'),
				'month' => $month,
				'year' => $currentYear,
				'daysInMonth' => $daysInMonth,
				'startPadding' => $startPadding,
			];
		}

		return view('employee.absences', compact('contact', 'absences', 'weeklyAvailability', 'months'));
	}

	public function toggleAbsenceDate(Request $request, $id)
	{
		$contact = Contact::findOrFail($id);
		$date = $request->date;

		$existingAbsence = $contact->absences()->where('date', $date)->first();

		if ($existingAbsence) {
			$existingAbsence->delete();
			$status = 'available';
		} else {
			$contact->absences()->create(['date' => $date]);
			$status = 'unavailable';
		}

		return response()->json(['status' => $status]);
	}

	public function updateWeeklyAvailability(Request $request, $id)
	{
		$contact = Contact::findOrFail($id);
		$weeklyAvailability = $contact->weeklyAvailability;

		if (!$weeklyAvailability) {
			$weeklyAvailability = $contact->weeklyAvailability()->create([]);
		}

		$weeklyAvailability->update($request->all());

		return response()->json(['status' => 'success']);
	}

	public function activity($id)
	{
		$contact = Contact::findOrFail($id);

		$activities = Activity::where('subject_type', Contact::class)
			->where('subject_id', $id)
			->orderBy('created_at', 'desc')
			->paginate(20);

		return view('employee.activity', compact('contact', 'activities'));
	}
}
