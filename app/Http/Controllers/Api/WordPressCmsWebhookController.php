<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PullPostFromWordPressJob;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives change notifications from the Humano CMS Sync WordPress plugin and queues a
 * pull of the affected item. Authenticated by a per-team shared secret.
 */
class WordPressCmsWebhookController extends Controller
{
    public function __invoke(Request $request, int $team): JsonResponse
    {
        $teamModel = Team::find($team);
        if (! $teamModel)
        {
            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        $expected = (string) $teamModel->getSetting('wordpress_webhook_secret');
        $provided = (string) ($request->header('X-Humano-Secret') ?? $request->input('secret', ''));

        if ($expected === '' || ! hash_equals($expected, $provided))
        {
            return response()->json(['success' => false, 'message' => 'Invalid secret'], 401);
        }

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'type' => ['required', 'string'],
            'action' => ['nullable', 'string'],
        ]);

        PullPostFromWordPressJob::dispatch(
            (int) $teamModel->id,
            (int) $validated['id'],
            (string) $validated['type'],
            (string) ($validated['action'] ?? 'updated'),
        );

        return response()->json(['success' => true]);
    }
}
