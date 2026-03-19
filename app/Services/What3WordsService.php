<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class What3WordsService
{
    /**
     * Geocode a free-text address to coordinates using Nominatim (OpenStreetMap).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocodeAddress(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Signals-Framework/1.0',
            ])->timeout(10)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Nominatim geocoding connection failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Nominatim geocoding returned non-2xx response', [
                'address' => $address,
                'status' => $response->status(),
            ]);

            return null;
        }

        $results = $response->json();

        if (empty($results) || ! isset($results[0]['lat'], $results[0]['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $results[0]['lat'],
            'lng' => (float) $results[0]['lon'],
        ];
    }

    /**
     * Convert a what3words address to coordinates.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function convertToCoordinates(string $words): ?array
    {
        $apiKey = settings('integrations.what3words_api_key');

        if (! $apiKey) {
            Log::debug('what3words API key is not configured');

            return null;
        }

        $response = Http::timeout(10)->get('https://api.what3words.com/v3/convert-to-coordinates', [
            'words' => $words,
            'key' => $apiKey,
        ]);

        if (! $response->successful()) {
            Log::warning('what3words API returned non-2xx response', [
                'words' => $words,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::warning('what3words API returned an error', [
                'words' => $words,
                'error' => $data['error'],
            ]);

            return null;
        }

        $coordinates = $data['coordinates'] ?? null;

        if (! $coordinates || ! isset($coordinates['lat'], $coordinates['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $coordinates['lat'],
            'lng' => (float) $coordinates['lng'],
        ];
    }
}
