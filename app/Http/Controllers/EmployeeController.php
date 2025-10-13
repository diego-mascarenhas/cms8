<?php

namespace App\Http\Controllers;

use App\DataTables\EmployeeDataTable;
use App\Models\Contact;
use App\Models\ContactStatus;
use App\Models\ContactWeeklyAvailability;
use App\Models\Language;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class EmployeeController extends Controller
{
    public function index(EmployeeDataTable $dataTable)
    {
        // Get employee statistics
        $dashboardStats = [
            'totalEmployees' => Contact::whereHas('user', function ($query)
            {
                $query->whereHas('roles', function ($q)
                {
                    $q->where('name', 'employee');
                });
            })->count(),
            'activeEmployees' => Contact::whereHas('user', function ($query)
            {
                $query->whereHas('roles', function ($q)
                {
                    $q->where('name', 'employee');
                });
            })->whereRaw("JSON_EXTRACT(data, '$.active') = true")->count(),
            'newThisWeek' => Contact::whereHas('user', function ($query)
            {
                $query->whereHas('roles', function ($q)
                {
                    $q->where('name', 'employee');
                });
            })->where('created_at', '>=', now()->subWeek())->count(),
        ];

        // Get statuses for filter
        $statuses = ContactStatus::all()->map(function ($status)
        {
            return ['id' => $status->id, 'name' => $status->name];
        })->toArray();

        // Get categories for filter (employees use the contacts module)
        // Since there are no specific categories for contacts, we'll use general categories or empty array
        $categories = \App\Models\Category::whereIn('module_id', [10, 14, 22]) // services, communications, mail
            ->orWhereNull('module_id')
            ->get()
            ->map(function ($category)
            {
                return ['id' => $category->id, 'name' => $category->name];
            })
            ->toArray();

        return $dataTable->render('employee.index', compact('dashboardStats', 'statuses', 'categories'));
    }

    public function create()
    {
        $statuses = ContactStatus::all()->map(function ($status)
        {
            return ['id' => $status->id, 'name' => $status->name];
        })->toArray();
        $languages = Language::all()->map(function ($language)
        {
            return ['id' => $language->code, 'name' => $language->name];
        })->toArray();
        $users = User::whereHas('roles', function ($query)
        {
            $query->where('name', 'employee');
        })->get()->map(function ($user)
        {
            return ['id' => $user->id, 'name' => $user->name];
        })->toArray();

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
        $statuses = ContactStatus::all()->map(function ($status)
        {
            return ['id' => $status->id, 'name' => $status->name];
        })->toArray();
        $languages = Language::all()->map(function ($language)
        {
            return ['id' => $language->code, 'name' => $language->name];
        })->toArray();
        $users = User::whereHas('roles', function ($query)
        {
            $query->where('name', 'employee');
        })->get()->map(function ($user)
        {
            return ['id' => $user->id, 'name' => $user->name];
        })->toArray();

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
        $data = $contact->data ?? (object) [];
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
        // Get specified employee contact
        $contact = Contact::findOrFail($id);

        // Get employee absences for the next 6 months
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

        return view('employee.absences', [
            'absences' => $absences,
            'weeklyAvailability' => $weeklyAvailability,
            'months' => $months,
            'employee' => $contact,
        ]);
    }

    public function toggleAbsenceDate(Request $request, $id)
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

    public function updateWeeklyAvailability(Request $request, $id)
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
