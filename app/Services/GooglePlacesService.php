<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    protected string $baseUrl = 'https://places.googleapis.com/v1/';

    protected string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.google.places_api_key', '');
    }

    /**
     * Search places by text query (e.g. "restaurant Madrid").
     *
     * @return array<int, array{id: string, name: string, formatted_address: string}>
     *
     * @throws \RuntimeException
     */
    public function searchText(string $textQuery, array $options = []): array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Google Places API key is not configured.');
        }

        $fieldMask = 'places.id,places.name,places.displayName,places.formattedAddress';

        $body = array_filter([
            'textQuery' => $textQuery,
            'pageSize' => $options['pageSize'] ?? 10,
            'languageCode' => $options['languageCode'] ?? app()->getLocale(),
        ]);

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'places:searchText', $body);

        if (! $response->successful())
        {
            throw new \RuntimeException(
                'Google Places API error: '.($response->json('error.message') ?? $response->body()),
                $response->status(),
            );
        }

        $data = $response->json();
        $places = $data['places'] ?? [];

        return array_values(array_map(function (array $place): array
        {
            $name = $place['name'] ?? '';
            $id = str_starts_with($name, 'places/') ? substr($name, 7) : $name;
            $displayName = $place['displayName']['text'] ?? $place['displayName'] ?? $name;

            return [
                'id' => $id,
                'name' => is_string($displayName) ? $displayName : ($displayName['text'] ?? ''),
                'formatted_address' => $place['formattedAddress'] ?? '',
            ];
        }, $places));
    }

    /**
     * Get place details and map to Enterprise-like array.
     *
     * @return array{name: string, address: string, locality: string, province: string, country: string, postal_code: string, phone: string, website: string}
     *
     * @throws \RuntimeException
     */
    public function getPlace(string $placeId): array
    {
        if (empty($this->apiKey))
        {
            throw new \RuntimeException('Google Places API key is not configured.');
        }

        $placeId = str_starts_with($placeId, 'places/') ? substr($placeId, 7) : $placeId;
        $fieldMask = 'displayName,formattedAddress,addressComponents,nationalPhoneNumber,internationalPhoneNumber,websiteUri';

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => $fieldMask,
        ])->get($this->baseUrl.'places/'.$placeId);

        if ($response->status() === 404 || (isset($response->json()['error'])))
        {
            throw new \RuntimeException('Place not found.', 404);
        }

        if (! $response->successful())
        {
            throw new \RuntimeException(
                'Google Places API error: '.($response->json('error.message') ?? $response->body()),
                $response->status(),
            );
        }

        $place = $response->json();
        $displayName = $place['displayName']['text'] ?? $place['displayName'] ?? '';
        $displayName = is_string($displayName) ? $displayName : '';

        $addressComponents = $place['addressComponents'] ?? [];
        $locality = $this->extractAddressComponent($addressComponents, 'locality');
        $province = $this->extractAddressComponent($addressComponents, 'administrative_area_level_1');
        $country = $this->extractAddressComponent($addressComponents, 'country');
        $postalCode = $this->extractAddressComponent($addressComponents, 'postal_code');
        $street = $this->extractAddressComponent($addressComponents, 'street_number').' '
            .$this->extractAddressComponent($addressComponents, 'route');
        $street = trim($street);
        if ($street === '')
        {
            $street = $place['formattedAddress'] ?? '';
        }

        return [
            'name' => $displayName,
            'address' => $street,
            'locality' => $locality,
            'province' => $province,
            'country' => $country,
            'postal_code' => $postalCode,
            'phone' => $place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? '',
            'website' => $place['websiteUri'] ?? '',
        ];
    }

    /**
     * @param  array<int, array{longText?: string, shortText?: string, types?: array<string>}>  $components
     */
    protected function extractAddressComponent(array $components, string $type): string
    {
        foreach ($components as $component)
        {
            $types = $component['types'] ?? [];
            if (in_array($type, $types, true))
            {
                return $component['longText'] ?? $component['shortText'] ?? '';
            }
        }

        return '';
    }
}
