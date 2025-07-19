<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Fare;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    /**
     * Get statistics for collaborator prices when a service is selected
     *
     * This endpoint calculates media (mean), moda (mode), and mediana (median)
     * statistics for collaborator prices for a specific service/fare.
     * It also provides additional statistics like min, max, range, and standard deviation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceStatistics(Request $request)
    {
        \Log::info('API Service statistics requested', ['service_id' => $request->service_id]);

        if (!$request->has('service_id') || !$request->service_id) {
            \Log::warning('API Service statistics: No service ID provided');
            return response()->json([
                'success' => false,
                'message' => 'Service ID is required'
            ], 400);
        }

        $serviceId = $request->service_id;

        // For public access, use a default team ID or get from request
        $teamId = $request->team_id ?? 1; // Default to team ID 1, or you can pass it as parameter

        \Log::info('API Service statistics: Processing', ['service_id' => $serviceId, 'team_id' => $teamId]);

        // Get all collaborators that have this service with prices
        $collaboratorsWithPrices = Contact::where('team_id', $teamId)
            ->whereHas('fares', function ($query) use ($serviceId) {
                $query->where('fares.id', $serviceId)
                    ->whereNotNull('contact_fare.price')
                    ->where('contact_fare.price', '>', 0);
            })
            ->with(['fares' => function ($query) use ($serviceId) {
                $query->where('fares.id', $serviceId)
                    ->whereNotNull('contact_fare.price')
                    ->where('contact_fare.price', '>', 0);
            }])
            ->get();

        \Log::info('API Service statistics: Found collaborators', ['count' => $collaboratorsWithPrices->count()]);

        if ($collaboratorsWithPrices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No collaborators found with prices for this service'
            ], 404);
        }

        // Debug: Log some sample prices to see what we're working with
        \Log::info('API Service statistics: Sample prices', [
            'sample_collaborators' => $collaboratorsWithPrices->take(3)->map(function($collaborator) use ($serviceId) {
                return [
                    'id' => $collaborator->id,
                    'name' => $collaborator->name,
                    'prices' => $collaborator->fares->where('id', $serviceId)->map(function($fare) {
                        return [
                            'price' => $fare->pivot->price,
                            'price_type' => gettype($fare->pivot->price),
                            'is_numeric' => is_numeric($fare->pivot->price)
                        ];
                    })->toArray()
                ];
            })->toArray()
        ]);

        // Extract all prices for this service
        $prices = [];
        foreach ($collaboratorsWithPrices as $collaborator) {
            foreach ($collaborator->fares as $fare) {
                // Ensure we have a valid numeric price
                $price = $fare->pivot->price;
                if (is_numeric($price) && $price > 0) {
                    $prices[] = (float) $price;
                }
            }
        }

        if (empty($prices)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid prices found for this service'
            ], 404);
        }

        // Calculate statistics
        $statistics = $this->calculatePriceStatistics($prices);

        // Get service name
        $service = Fare::find($serviceId);
        $serviceName = $service ? $service->name : 'Unknown Service';

        return response()->json([
            'success' => true,
            'service_name' => $serviceName,
            'total_collaborators' => count($prices),
            'statistics' => $statistics
        ]);
    }

    /**
     * Calculate price statistics (media, moda, mediana)
     */
    private function calculatePriceStatistics(array $prices)
    {
        sort($prices);
        $count = count($prices);

        // Media (Mean)
        $media = array_sum($prices) / $count;

        // Mediana (Median)
        $mediana = 0;
        if ($count % 2 == 0) {
            // Even number of elements
            $mediana = ($prices[($count / 2) - 1] + $prices[$count / 2]) / 2;
        } else {
            // Odd number of elements
            $mediana = $prices[floor($count / 2)];
        }

        // Moda (Mode) - most frequent value
        $moda = null;
        if (!empty($prices)) {
            // Convert all prices to strings to ensure array_count_values works properly
            $priceStrings = array_map('strval', $prices);
            $priceCounts = array_count_values($priceStrings);

            if (!empty($priceCounts)) {
                $maxCount = max($priceCounts);
                $modaKeys = array_keys($priceCounts, $maxCount);

                // Convert back to floats
                $moda = array_map('floatval', $modaKeys);

                // If all values are unique, there's no mode
                if ($maxCount == 1) {
                    $moda = null;
                }
            }
        }

        // Additional statistics
        $min = min($prices);
        $max = max($prices);
        $range = $max - $min;

        // Standard deviation
        $variance = 0;
        foreach ($prices as $price) {
            $variance += pow($price - $media, 2);
        }
        $variance = $variance / $count;
        $standardDeviation = sqrt($variance);

        return [
            'media' => round($media, 2),
            'mediana' => round($mediana, 2),
            'moda' => $moda ? array_map(function($value) { return round($value, 2); }, $moda) : null,
            'min' => round($min, 2),
            'max' => round($max, 2),
            'range' => round($range, 2),
            'standard_deviation' => round($standardDeviation, 2),
            'count' => $count
        ];
    }
}
