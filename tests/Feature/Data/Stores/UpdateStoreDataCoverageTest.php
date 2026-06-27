<?php

use App\Data\Stores\UpdateStoreData;
use App\Models\Country;
use Illuminate\Validation\ValidationException;

describe('UpdateStoreData', function () {
    it('validates a well-formed partial update and keeps the provided fields', function () {
        $data = UpdateStoreData::validateAndCreate([
            'name' => 'Renamed Depot',
            'street' => '1 High Street',
            'city' => 'Bristol',
            'county' => 'Avon',
            'postcode' => 'BS1 4ST',
            'country_code' => 'GB',
            'phone' => '0117 000 0000',
            'email' => 'depot@example.com',
            'is_default' => true,
            'tag_list' => ['hq', 'south-west'],
        ]);

        expect($data->name)->toBe('Renamed Depot')
            ->and($data->city)->toBe('Bristol')
            ->and($data->country_code)->toBe('GB')
            ->and($data->email)->toBe('depot@example.com')
            ->and($data->is_default)->toBeTrue()
            ->and($data->tag_list)->toBe(['hq', 'south-west']);
    });

    it('rejects an invalid email', function () {
        expect(fn () => UpdateStoreData::validateAndCreate(['email' => 'not-an-email']))
            ->toThrow(ValidationException::class);
    });

    it('rejects a two-character country_code violation and an over-length postcode', function () {
        expect(fn () => UpdateStoreData::validateAndCreate(['country_code' => 'GBR']))
            ->toThrow(ValidationException::class);

        expect(fn () => UpdateStoreData::validateAndCreate(['postcode' => str_repeat('X', 21)]))
            ->toThrow(ValidationException::class);
    });

    it('rejects a country_id that does not exist but accepts a real one', function () {
        expect(fn () => UpdateStoreData::validateAndCreate(['country_id' => 999999]))
            ->toThrow(ValidationException::class);

        $country = Country::query()->first() ?? Country::factory()->create();

        $data = UpdateStoreData::validateAndCreate(['country_id' => $country->id]);
        expect($data->country_id)->toBe($country->id);
    });

    it('rejects a non-array tag_list', function () {
        expect(fn () => UpdateStoreData::validateAndCreate(['tag_list' => 'just-a-string']))
            ->toThrow(ValidationException::class);
    });
});
