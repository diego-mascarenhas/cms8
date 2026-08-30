<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TeamSiteAssistantPromptService;
use Illuminate\Http\JsonResponse;

class PublicSiteAssistantController extends Controller
{
    public function show(string $teamSlug, TeamSiteAssistantPromptService $siteAssistant): JsonResponse
    {
        $embed = $siteAssistant->publicEmbedForSlug($teamSlug);
        if (! $embed)
        {
            return response()->json([
                'success' => false,
                'message' => __('Automation not found.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'embed' => $embed,
            ],
        ]);
    }
}
