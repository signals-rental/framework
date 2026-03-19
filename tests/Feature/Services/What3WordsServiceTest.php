<?php

use App\Services\What3WordsService;
use Illuminate\Support\Facades\Http;

it('convertToCoordinates returns null when no API key is configured', function () {
    settings()->set('integrations.what3words_api_key', '');

    $service = new What3WordsService;
    $result = $service->convertToCoordinates('filled.count.soap');

    expect($result)->toBeNull();
});

it('convertToCoordinates returns coordinates on successful response', function () {
    settings()->set('integrations.what3words_api_key', 'test-api-key');

    Http::fake([
        'api.what3words.com/*' => Http::response([
            'coordinates' => [
                'lat' => 51.520847,
                'lng' => -0.195521,
            ],
        ], 200),
    ]);

    $service = new What3WordsService;
    $result = $service->convertToCoordinates('filled.count.soap');

    expect($result)->toBe([
        'lat' => 51.520847,
        'lng' => -0.195521,
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.what3words.com/v3/convert-to-coordinates')
            && $request['words'] === 'filled.count.soap'
            && $request['key'] === 'test-api-key';
    });
});

it('convertToCoordinates returns null on API error response', function () {
    settings()->set('integrations.what3words_api_key', 'test-api-key');

    Http::fake([
        'api.what3words.com/*' => Http::response([
            'error' => [
                'code' => 'BadWords',
                'message' => 'Invalid what3words address',
            ],
        ], 200),
    ]);

    $service = new What3WordsService;
    $result = $service->convertToCoordinates('invalid.words.here');

    expect($result)->toBeNull();
});

it('convertToCoordinates returns null on HTTP failure', function () {
    settings()->set('integrations.what3words_api_key', 'test-api-key');

    Http::fake([
        'api.what3words.com/*' => Http::response(null, 500),
    ]);

    $service = new What3WordsService;
    $result = $service->convertToCoordinates('filled.count.soap');

    expect($result)->toBeNull();
});

it('convertToCoordinates returns null when coordinates are missing from response', function () {
    settings()->set('integrations.what3words_api_key', 'test-api-key');

    Http::fake([
        'api.what3words.com/*' => Http::response(['country' => 'GB'], 200),
    ]);

    $service = new What3WordsService;
    $result = $service->convertToCoordinates('filled.count.soap');

    expect($result)->toBeNull();
});

it('geocodeAddress returns coordinates on successful Nominatim response', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'lat' => '51.5074',
                'lon' => '-0.1278',
                'display_name' => 'London, UK',
            ],
        ], 200),
    ]);

    $service = new What3WordsService;
    $result = $service->geocodeAddress('London, UK');

    expect($result)->toBe([
        'lat' => 51.5074,
        'lng' => -0.1278,
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'nominatim.openstreetmap.org/search')
            && $request['q'] === 'London, UK'
            && $request['format'] === 'json';
    });
});

it('geocodeAddress returns null on empty results', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([], 200),
    ]);

    $service = new What3WordsService;
    $result = $service->geocodeAddress('NonexistentPlace12345');

    expect($result)->toBeNull();
});

it('geocodeAddress returns null on HTTP failure', function () {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response(null, 500),
    ]);

    $service = new What3WordsService;
    $result = $service->geocodeAddress('London, UK');

    expect($result)->toBeNull();
});
