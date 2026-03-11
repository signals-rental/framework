<?php

namespace App\Data\Members;

use App\Models\Member;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class MemberData extends Data
{
    /**
     * @param  list<string>|null  $tag_list
     * @param  array<string, mixed>|null  $custom_fields
     * @param  list<array<string, mixed>>|null  $addresses
     * @param  list<array<string, mixed>>|null  $emails
     * @param  list<array<string, mixed>>|null  $phones
     * @param  list<array<string, mixed>>|null  $links
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $membership_type,
        public bool $is_active,
        public ?string $description,
        public ?string $locale,
        public ?string $default_currency_code,
        public ?int $organisation_tax_class_id,
        public ?array $tag_list,
        public ?string $icon_url,
        public ?string $icon_thumb_url,
        public string $created_at,
        public string $updated_at,
        public ?string $deleted_at,
        public ?array $custom_fields = null,
        public ?array $addresses = null,
        public ?array $emails = null,
        public ?array $phones = null,
        public ?array $links = null,
    ) {}

    public static function fromModel(Member $member): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $member->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $member->updated_at;

        /** @var Carbon|null $deletedAt */
        $deletedAt = $member->deleted_at;

        return new self(
            id: $member->id,
            name: $member->name,
            membership_type: (string) $member->getRawOriginal('membership_type'),
            is_active: $member->is_active,
            description: $member->description,
            locale: $member->locale,
            default_currency_code: $member->default_currency_code,
            organisation_tax_class_id: $member->organisation_tax_class_id,
            tag_list: $member->getAttribute('tag_list'),
            icon_url: $member->icon_url,
            icon_thumb_url: $member->icon_thumb_url,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            deleted_at: $deletedAt?->toIso8601String(),
            custom_fields: $member->relationLoaded('customFieldValues') ? $member->custom_fields : null,
            addresses: $member->relationLoaded('addresses')
                ? $member->addresses->map(fn ($a): array => AddressData::fromModel($a)->toArray())->all()
                : null,
            emails: $member->relationLoaded('emails')
                ? $member->emails->map(fn ($e): array => EmailData::fromModel($e)->toArray())->all()
                : null,
            phones: $member->relationLoaded('phones')
                ? $member->phones->map(fn ($p): array => PhoneData::fromModel($p)->toArray())->all()
                : null,
            links: $member->relationLoaded('links')
                ? $member->links->map(fn ($l): array => LinkData::fromModel($l)->toArray())->all()
                : null,
        );
    }
}
