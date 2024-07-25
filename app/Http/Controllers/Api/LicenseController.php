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
			'commit' => 'nullable|string',
		]);

		$normalizedUrl = preg_replace('/^https?:\/\//', '', $data['url']);

		$license = License::where('key', $data['key'])
			->where('url', 'like', "%$normalizedUrl%")
			->first();

		try
		{
			if ($license)
			{
				$license->update($data);
				$license->touch();
				return response()->json(['message' => 'License updated successfully'], 200);
			}
			else
			{
				License::create($data);
				return response()->json(['message' => 'Application registered successfully'], 201);
			}
		}
		catch (\Exception $e)
		{
			Log::error('Error creating or updating license: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to register application'], 500);
		}
	}
}