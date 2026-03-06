<?php

namespace App\Http\Controllers;

use App\Models\Enterprise;
use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GooglePlacesController extends Controller
{
    /**
     * Search places by text query.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('create', Enterprise::class);

        $validated = $request->validate([
            'text_query' => 'required|string|max:500',
        ]);

        try
        {
            $service = new GooglePlacesService;
            $places = $service->searchText($validated['text_query']);

            return response()->json(['places' => $places]);
        } catch (\RuntimeException $e)
        {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? (int) $e->getCode() : 502;

            return response()->json(
                ['message' => $e->getMessage()],
                $status,
            );
        }
    }

    /**
     * Get place details for pre-filling enterprise/client form.
     */
    public function placeDetails(string $placeId): JsonResponse
    {
        $this->authorize('create', Enterprise::class);

        $placeId = trim($placeId);
        if ($placeId === '')
        {
            return response()->json(['message' => 'Place ID is required.'], 422);
        }

        try
        {
            $service = new GooglePlacesService;
            $place = $service->getPlace($placeId);

            return response()->json($place);
        } catch (\RuntimeException $e)
        {
            $status = $e->getCode() === 404 ? 404 : 502;

            return response()->json(
                ['message' => $e->getMessage()],
                $status,
            );
        }
    }

    /**
     * Fetch place details, store in session, and redirect to client create form.
     */
    public function useForClient(Request $request): RedirectResponse
    {
        $this->authorize('create', Enterprise::class);

        $validated = $request->validate([
            'place_id' => 'required|string|max:500',
        ]);

        try
        {
            $service = new GooglePlacesService;
            $place = $service->getPlace($validated['place_id']);
            $placeData = $place;
            unset($placeData['api_response']);
            session()->flash('place_data', $placeData);

            return redirect()->route('client.create');
        } catch (\RuntimeException $e)
        {
            return redirect()
                ->route('enterprise.index')
                ->with('error', $e->getMessage());
        }
    }
}
