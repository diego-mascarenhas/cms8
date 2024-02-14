<?php

namespace App\Http\Controllers\sys;

use App\Http\Controllers\Controller;
use App\Models\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
  /**
   * Redirect to user-management view.
   *
   */
  public function WebhookManagement()
  {
    $users = Webhooks::all();
    $userCount = $users->count();
    $usersUnique = $users->unique(['email']);
    $userDuplicates = $users->diff($usersUnique)->count();

    return view('cms.webhooks', [
      'totalUser' => $userCount,
      'userDuplicates' => $userDuplicates,
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
      4 => 'email',
    ];

    $search = [];

    $totalData = Webhooks::count();

    $totalFiltered = $totalData;

    $limit = $request->input('length');
    $start = $request->input('start');
    $order = $columns[$request->input('order.0.column')];
    $dir = $request->input('order.0.dir');

    if (empty($request->input('search.value')))
    {
      $users = Webhooks::offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();
    }
    else
    {
      $search = $request->input('search.value');

      $users = Webhooks::where('id', 'LIKE', "%{$search}%")
        ->orWhere('name', 'LIKE', "%{$search}%")
        ->orWhere('email', 'LIKE', "%{$search}%")
        ->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();

      $totalFiltered = Webhooks::where('id', 'LIKE', "%{$search}%")
        ->orWhere('name', 'LIKE', "%{$search}%")
        ->orWhere('email', 'LIKE', "%{$search}%")
        ->count();
    }

    $data = [];

    if (!empty($users))
    {
      $ids = $start;

      foreach ($users as $user)
      {
        $nestedData['id'] = $user->id;
        $nestedData['fake_id'] = ++$ids;
        $nestedData['name'] = $user->name;
        $nestedData['email'] = $user->email;

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
    $name = $request->input('fields.name.value');
    $email = $request->input('fields.email.value');

    $validator = Validator::make($request->all(), [
      'fields.name.value' => 'sometimes|required|string|max:100',
      'fields.email.value' => 'sometimes|required|email',
    ]);

    if ($validator->fails())
    {
      return response()->json($validator->errors(), 400);
    }

    $res = Webhooks::create([
      'name' => ($name) ? $name : 'No se ha especificado',
      'email' => ($email) ? $email : 'No se ha especificado',
      'data' => $request->all(),
    ]);

    if ($request->input('fields.retURL.value'))
    {
      return redirect()->away($request->input('fields.retURL.value'));
    }
    else
    {
      return response()->json($res, 201);
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
    $where = ['id' => $id];

    $data = Webhooks::where($where)->first();

    return response()->json($data);
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id)
  {
    $where = ['id' => $id];

    $users = Webhooks::where($where)->first();

    return response()->json($users);
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
    $users = Webhooks::where('id', $id)->delete();
  }
}
