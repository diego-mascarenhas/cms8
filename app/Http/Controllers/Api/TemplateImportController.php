<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TemplateImportController extends Controller
{
    public function fetchHtml(Request $request)
    {
        $url = $request->query('url');
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL))
        {
            return response()->json(['error' => 'Invalid URL'], 400);
        }

        try
        {
            $response = Http::get($url);
            if ($response->successful())
            {
                return response()->json(['html' => $response->body()]);
            } else
            {
                return response()->json(['error' => 'Failed to fetch HTML'], 400);
            }
        } catch (\Exception $e)
        {
            return response()->json(['error' => 'Exception: '.$e->getMessage()], 500);
        }
    }
}
