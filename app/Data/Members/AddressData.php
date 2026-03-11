<?php

namespace App\Data\Members;

use App\Models\Address;
use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $street,
        public ?string $city,
        public ?string $county,
        public ?string $postcode,
        public ?int $country_id,
        public ?int $type_id,
        public bool $is_primary,
        public ?string $latitude,
        public ?string $longitude,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Address $address): self
    {
        return new self(
            id: $address->id,
            name: $address->name,
            street: $address->street,
            city: $address->city,
            county: $address->county,
            postcode: $address->postcode,
            country_id: $address->country_id,
            type_id: $address->type_id,
            is_primary: $address->is_primary,
            latitude: $address->latitude !== null ? (string) $address->latitude : null,
            longitude: $address->longitude !== null ? (string) $address->longitude : null,
            created_at: $address->created_at->toIso8601String(),
            updated_at: $address->updated_at->toIso8601String(),
        );
    }
}
