<?php

namespace App\Http\Controllers\laravel_example;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

use Log;

class UserManagement extends Controller
{
  /**
   * Redirect to user-management view.
   *
   */
  public function UserManagement()
  {
    $users = User::whereHas('teams', function($query) {
      $query->where('team_id', Auth::user()->currentTeam->id);
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

    // Filter users by current team
    $baseQuery = User::whereHas('teams', function($q) {
      $q->where('team_id', Auth::user()->currentTeam->id);
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
      
      $users = $searchQuery->where(function($q) use ($search) {
          $q->where('id', 'LIKE', "%{$search}%")
            ->orWhere('name', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%");
        })
        ->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();

      $totalFiltered = $baseQuery->where(function($q) use ($search) {
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
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
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
        
      if ($existingUser) {
        return response()->json(['message' => "already exits"], 422);
      }
      
      $user->update($data);
      
      // Update user roles if provided
      if($request->role) {
        $user->syncRoles([$request->role]);
      }

      // user updated
      return response()->json('Updated');
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
        
        // Add the user to the current team
        $user->teams()->attach(Auth::user()->currentTeam->id);
        
        // Assign role if provided
        if($request->role) {
          $user->assignRole($request->role);
        }

        // user created
        return response()->json('Created');
      }
      else
      {
        // user already exist
        return response()->json(['message' => "already exits"], 422);
      }
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
    $user = User::findOrFail($id);
    
    // Get the user's first role (ID)
    $userRole = $user->roles->first();
    $user->role = $userRole ? $userRole->id : null;
    
    // Convert phone to string to avoid type issues
    if ($user->phone) {
      $user->phone = (string)$user->phone;
    }

    return response()->json($user);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
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
