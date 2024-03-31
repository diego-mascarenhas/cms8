<?php

namespace App\Http\Controllers\trade;

use App\Http\Controllers\Controller;
use App\Models\TradeData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TradeDataController extends Controller
{
	public function store(Request $request)
	{
		$data = $request->all();

		$validatedData = Validator::make($data, [
			'symbol' => 'string|max:25|required',
			'time_frame' => 'numeric',
			'open' => 'required',
			'close' => 'required',
			'low' => 'required',
			'high' => 'required'
		]);

		if ($validatedData->fails())
		{
			return response()->json($validatedData->errors(), 400);
		}

		$res = TradeData::create($data);

		return response()->json($res, 201);
	}
}