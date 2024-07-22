<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;

use Log;

class LicenseController extends Controller
{
	public function register(Request $request)
	{
		$data = $request->validate([
			'name' => 'required|string',
			'env' => 'nullable|string',
			'key' => 'nullable|string',
			'debug' => 'nullable|string',
			'url' => 'nullable|url',
		]);

		try
		{
			License::create($data);
			return response()->json(['message' => 'Application registered successfully'], 200);
		}
		catch (\Exception $e)
		{
			Log::error('Error creating license: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to register application'], 500);
		}
	}
}