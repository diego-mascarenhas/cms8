<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SysRequest;
use Illuminate\Support\Facades\Validator;

class SysRequestController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $sysRequest = SysRequest::create([
            'data' => $request->all(),
        ]);

        return response()->json($sysRequest, 201);
    }
}
