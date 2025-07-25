<?php

namespace App\Http\Controllers\laravel_example;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Log;
use Spatie\Permission\Models\Role;

class UserManagement extends Controller
{
	/**
	 * Redirect to user-management view.
	 */
	public function UserManagement()
	{
		$users = User::whereHas('teams', function ($query)
		{
			$query->where('team_id', Auth::user()->currentTeam->id);
		})->whereHas('roles', function ($query)
		{
			$query->where('name', 'admin');
		})->get();

		$userCount = $users->count();
		$verified = $users->whereNotNull('email_verified_at')->count();
		$notVerified = $users->whereNull('email_verified_at')->count();
		$usersUnique = $users->unique(['email']);
		$userDuplicates = $users->diff($usersUnique)->count();

		$roles = Role::all();

		return view('content.laravel-example.user-management', [
			'totalUser' => $userCount,
			'verified' => $verified,
			'notVerified' => $notVerified,
			'userDuplicates' => $userDuplicates,
			'roles' => $roles,
		]);
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request)
	{
		$columns = [
			1 => 'id',
			2 => 'name',
			3 => 'email',
			4 => 'email_verified_at',
		];

		$search = [];

		// Filter users by current team and admin role
		$baseQuery = User::with('roles')->whereHas('teams', function ($q)
		{
			$q->where('team_id', Auth::user()->currentTeam->id);
		})->whereHas('roles', function ($q)
		{
			$q->where('name', 'admin');
		});

		$totalData = $baseQuery->count();
		$totalFiltered = $totalData;

		$limit = $request->input('length');
		$start = $request->input('start');
		$order = $columns[$request->input('order.0.column')];
		$dir = $request->input('order.0.dir');

		if (empty($request->input('search.value')))
		{
			$users = $baseQuery->offset($start)
				->limit($limit)
				->orderBy($order, $dir)
				->get();
		}
		else
		{
			$search = $request->input('search.value');

			$searchQuery = clone $baseQuery;

			$users = $searchQuery->where(function ($q) use ($search)
			{
				$q->where('id', 'LIKE', "%{$search}%")
					->orWhere('name', 'LIKE', "%{$search}%")
					->orWhere('email', 'LIKE', "%{$search}%");
			})
				->offset($start)
				->limit($limit)
				->orderBy($order, $dir)
				->get();

			$totalFiltered = $baseQuery->where(function ($q) use ($search)
			{
				$q->where('id', 'LIKE', "%{$search}%")
					->orWhere('name', 'LIKE', "%{$search}%")
					->orWhere('email', 'LIKE', "%{$search}%");
			})
				->count();
		}

		$data = [];

		if (!empty($users))
		{
			// providing a dummy id instead of database ids
			$ids = $start;

			foreach ($users as $user)
			{
				$nestedData['id'] = $user->id;
				$nestedData['fake_id'] = ++$ids;
				$nestedData['name'] = $user->name;
				$nestedData['email'] = $user->email;
				$nestedData['email_verified_at'] = $user->email_verified_at;
				$nestedData['roles'] = $user->roles->pluck('name'); // Get user roles

				$data[] = $nestedData;
			}
		}

		if ($data)
		{
			return response()->json([
				'draw' => intval($request->input('draw')),
				'recordsTotal' => intval($totalData),
				'recordsFiltered' => intval($totalFiltered),
				'code' => 200,
				'data' => $data,
			]);
		}
		else
		{
			return response()->json([
				'message' => 'Internal Server Error',
				'code' => 500,
				'data' => [],
			]);
		}
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		//
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		try
		{
			Log::info(json_encode($request->all()));

			$userID = $request->id;

			if ($userID)
			{
				// update the value
				$user = User::findOrFail($userID);

				$data = [
					'name' => $request->name,
					'email' => $request->email,
				];

				if ($request->userContact)
				{
					$data['phone'] = preg_replace('/\D/', '', $request->userContact);
				}
				else
				{
					$data['phone'] = null;
				}

				// Check if email is already used by another user
				$existingUser = User::where('email', $request->email)
					->where('id', '!=', $userID)
					->first();

				if ($existingUser)
				{
					return response()->json(['message' => 'already exits'], 422);
				}

				$user->update($data);

				// Update user roles if provided
				if ($request->has('role') && $request->role)
				{
					// Find the role by ID
					$role = \Spatie\Permission\Models\Role::find($request->role);
					if ($role)
					{
						// Assign the role by name
						$user->syncRoles([$role->name]);
						Log::info("Role assigned: {$role->name}");
					}
					else
					{
						Log::warning("Role not found with ID: {$request->role}");
					}
				}

				// Return the updated user data
				$user->fresh();
				$user->load('roles');
				$user->role = $user->roles->first() ? $user->roles->first()->id : null;

				// Log the final user state
				Log::info('Updated user state:', [
					'user_id' => $user->id,
					'roles' => $user->roles->pluck('name', 'id'),
					'role_id_sent' => $user->role,
				]);

				// user updated
				return response()->json([
					'status' => 'Updated',
					'user' => $user,
				]);
			}
			else
			{
				// create new one if email is unique
				$userEmail = User::where('email', $request->email)->first();

				if (empty($userEmail))
				{
					$data = [
						'name' => $request->name,
						'email' => $request->email,
						'password' => bcrypt(Str::random(10)),
					];

					if ($request->userContact)
					{
						$data['phone'] = preg_replace('/\D/', '', $request->userContact);
					}
					else
					{
						$data['phone'] = null;
					}

					$user = User::create($data);

					// Get the current team and add the user to it
					$currentTeam = Auth::user()->currentTeam;
					$user->teams()->attach($currentTeam->id);

					// Get role ID from request or find guest role by default
					$roleId = $request->role;
					if (empty($roleId))
					{
						$guestRole = \Spatie\Permission\Models\Role::where('name', 'guest')->first();
						if ($guestRole)
						{
							$roleId = $guestRole->id;
						}
					}

					// Assign role
					if ($roleId)
					{
						// Find the role by ID
						$role = \Spatie\Permission\Models\Role::find($roleId);
						if ($role)
						{
							// Assign the role by name
							$user->assignRole($role->name);
							Log::info("Role assigned: {$role->name}");
						}
						else
						{
							Log::warning("Role not found with ID: {$roleId}");
						}
					}

					// Queue welcome email with password setup link (asynchronous)
					try
					{
						\App\Jobs\SendNewUserWelcomeEmail::dispatch($user, $currentTeam);
						Log::info("Welcome email job queued for: {$user->email}");
					}
					catch (\Exception $e)
					{
						Log::error('Failed to queue welcome email: ' . $e->getMessage());
						// Don't fail the user creation if email queueing fails
					}

					// Return the created user data
					$user->fresh();
					$user->load('roles');
					$user->role = $user->roles->first() ? $user->roles->first()->id : null;

					// Log the final user state
					Log::info('Created user state:', [
						'user_id' => $user->id,
						'team_id' => $currentTeam->id,
						'team_name' => $currentTeam->name,
						'roles' => $user->roles->pluck('name', 'id'),
						'role_id_sent' => $user->role,
					]);

					// user created
					return response()->json([
						'status' => 'Created',
						'user' => $user,
						'message' => 'Usuario creado exitosamente. Se ha enviado un email con instrucciones para configurar la contraseña.',
					]);
				}
				else
				{
					// user already exist
					return response()->json(['message' => 'already exits'], 422);
				}
			}
		}
		catch (\Exception $e)
		{
			Log::error('Error saving user data: ' . $e->getMessage());

			return response()->json(['message' => 'Error processing request: ' . $e->getMessage()], 500);
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id)
	{
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id)
	{
		try
		{
			$user = User::with('roles')->findOrFail($id);

			// Get all roles for debugging
			$allRoles = \Spatie\Permission\Models\Role::all();
			Log::info('All available roles: ' . $allRoles->pluck('name', 'id'));

			// Get the user's first role (ID)
			$userRole = $user->roles->first();
			$user->role = $userRole ? $userRole->id : null;

			// Log role information for debugging
			Log::info('User role data:', [
				'user_id' => $user->id,
				'role_id' => $user->role,
				'role_name' => $userRole ? $userRole->name : 'none',
			]);

			// Convert phone to string to avoid type issues
			if ($user->phone)
			{
				$user->phone = (string) $user->phone;
			}

			return response()->json($user);
		}
		catch (\Exception $e)
		{
			Log::error('Error fetching user data: ' . $e->getMessage());

			return response()->json(['error' => 'User not found'], 404);
		}
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id)
	{
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id)
	{
		$users = User::where('id', $id)->delete();
	}
}
