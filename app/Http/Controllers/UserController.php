<?php

namespace App\Http\Controllers;

use App\DataTables\UserDataTable;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
	/**
	 * Display a listing of the users.
	 */
	public function index(UserDataTable $dataTable)
	{
		$users = User::whereHas('teams', function ($query) {
			$query->where('team_id', Auth::user()->currentTeam->id);
		})->whereHas('roles', function ($query) {
			$query->where('name', 'admin');
		})->get();

		$userCount = $users->count();
		$verified = $users->whereNotNull('email_verified_at')->count();
		$notVerified = $users->whereNull('email_verified_at')->count();
		$usersUnique = $users->unique(['email']);
		$userDuplicates = $users->diff($usersUnique)->count();

		$roles = Role::all();

		return $dataTable->render('user.index', [
			'totalUser' => $userCount,
			'verified' => $verified,
			'notVerified' => $notVerified,
			'userDuplicates' => $userDuplicates,
			'roles' => $roles,
		]);
	}

	/**
	 * Show the form for creating a new user.
	 */
	public function create()
	{
		$roles = Role::all();

		return view('user.form', [
			'roles' => $roles,
		]);
	}

	/**
	 * Store a newly created user in storage.
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => [
				'required',
				'email',
				'max:255',
				Rule::unique('users')->where(function ($query) {
					return $query->whereHas('teams', function ($q) {
						$q->where('team_id', Auth::user()->currentTeam->id);
					});
				})
			],
			'password' => 'required|string|min:8|confirmed',
			'role_id' => 'required|exists:roles,id',
		]);

		$user = User::create([
			'name' => $validated['name'],
			'email' => $validated['email'],
			'password' => Hash::make($validated['password']),
		]);

		// Assign role
		$role = Role::findById($validated['role_id']);
		$user->assignRole($role);

		// Add user to current team
		$user->teams()->attach(Auth::user()->currentTeam->id);

		return redirect()->route('user.index')
			->with('success', __('User created successfully.'));
	}

	/**
	 * Display the specified user.
	 */
	public function show(User $user)
	{
		// Allow users to view themselves or if they have permission
		if (!Auth::user()->can('user.show') && Auth::id() !== $user->id) {
			abort(403);
		}

		// Verify user belongs to current team (unless viewing yourself)
		if (Auth::id() !== $user->id && !$user->teams->contains(Auth::user()->currentTeam->id)) {
			abort(404);
		}

		return view('user.show', compact('user'));
	}

	/**
	 * Show the form for editing the specified user.
	 */
	public function edit(User $user)
	{
		// Allow users to edit themselves or if they have permission
		if (!Auth::user()->can('user.edit') && Auth::id() !== $user->id) {
			abort(403);
		}

		// Verify user belongs to current team (unless editing yourself)
		if (Auth::id() !== $user->id && !$user->teams->contains(Auth::user()->currentTeam->id)) {
			abort(404);
		}

		$roles = Role::all();

		return view('user.form', [
			'data' => $user,
			'roles' => $roles,
		]);
	}

	/**
	 * Update the specified user in storage.
	 */
	public function update(Request $request, User $user)
	{
		// Allow users to update themselves or if they have permission
		if (!Auth::user()->can('user.update') && Auth::id() !== $user->id) {
			abort(403);
		}

		// Verify user belongs to current team (unless updating yourself)
		if (Auth::id() !== $user->id && !$user->teams->contains(Auth::user()->currentTeam->id)) {
			abort(404);
		}

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'email' => [
				'required',
				'email',
				'max:255',
				Rule::unique('users')->ignore($user->id)->where(function ($query) {
					return $query->whereHas('teams', function ($q) {
						$q->where('team_id', Auth::user()->currentTeam->id);
					});
				})
			],
			'password' => 'nullable|string|min:8|confirmed',
			'role_id' => 'required|exists:roles,id',
		]);

		$updateData = [
			'name' => $validated['name'],
			'email' => $validated['email'],
		];

		if (!empty($validated['password'])) {
			$updateData['password'] = Hash::make($validated['password']);
		}

		$user->update($updateData);

		// Update role
		$role = Role::findById($validated['role_id']);
		$user->syncRoles([$role]);

		return redirect()->route('user.index')
			->with('success', __('User updated successfully.'));
	}

	/**
	 * Remove the specified user from storage.
	 */
	public function destroy($id)
	{
		$user = User::findOrFail($id);

		// Verify user belongs to current team
		if (!$user->teams->contains(Auth::user()->currentTeam->id)) {
			abort(404);
		}

		// Prevent deleting yourself
		if ($user->id === Auth::id()) {
			return response()->json(['error' => __('You cannot delete yourself.')], 422);
		}

		// Remove from team instead of deleting completely
		$user->teams()->detach(Auth::user()->currentTeam->id);

		return response()->json(['success' => __('User removed from team successfully.')], 200);
	}
}
