<?php

namespace App\Data\Members;

use App\Data\Concerns\FormatsTimestamps;
use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;

class MemberData extends Data
{
    use FormatsTimestamps;

    /**
     * @param  list<string>  $tag_list
     * @param  array<string, mixed>  $custom_fields
     * @param  array<string, mixed>  $membership
     * @param  array<string, mixed>|null  $primary_address
     * @param  list<array<string, mixed>>  $addresses
     * @param  list<array<string, mixed>>  $emails
     * @param  list<array<string, mixed>>  $phones
     * @param  list<array<string, mixed>>  $links
     * @param  list<array<string, mixed>>  $child_members
     * @param  list<array<string, mixed>>  $parent_members
     * @param  array<string, mixed>|null  $icon
     * @param  array<string, mixed>|null  $identity
     * @param  list<array<string, mixed>>  $service_stock_levels
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $membership_type,
        #[MapOutputName('active')]
        public bool $is_active,
        public string $description,
        public bool $bookable,
        public int $location_type,
        public ?string $locale,
        public string $day_cost,
        public string $hour_cost,
        public string $distance_cost,
        public string $flat_rate_cost,
        public int $membership_id,
        public ?int $lawful_basis_type_id,
        public ?string $lawful_basis_type_name,
        public ?int $sale_tax_class_id,
        public ?string $sale_tax_class_name,
        public ?int $purchase_tax_class_id,
        public ?string $purchase_tax_class_name,
        public array $tag_list,
        public array $custom_fields,
        #[MapOutputName('icon_exists?')]
        public bool $icon_exists,
        public ?string $mapping_id,
        public string $created_at,
        public string $updated_at,
        public array $membership = [],
        public ?array $primary_address = null,
        public ?array $icon = null,
        public ?array $identity = null,
        public array $addresses = [],
        public array $emails = [],
        public array $phones = [],
        public array $links = [],
        public array $service_stock_levels = [],
        public array $child_members = [],
        public array $parent_members = [],
    ) {}

    public static function fromModel(Member $member): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $member->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $member->updated_at;

        $membershipData = self::buildMembershipData($member);

        $addresses = [];
        $primaryAddress = null;
        if ($member->relationLoaded('addresses')) {
            $primary = $member->addresses->firstWhere('is_primary', true);
            $primaryAddress = $primary ? AddressData::fromModel($primary)->toArray() : null;
            $addresses = $member->addresses
                ->reject(fn ($a): bool => $a->is_primary)
                ->map(fn ($a): array => AddressData::fromModel($a)->toArray())
                ->values()
                ->all();
        }

        $childMembers = [];
        $parentMembers = [];
        /** @var MembershipType $memberType */
        $memberType = $member->membership_type;
        $memberTypeName = $memberType->label();
        if ($member->relationLoaded('contacts')) {
            /** @var \Illuminate\Support\Collection<int, Member> $contactsList */
            $contactsList = $member->contacts;
            $childMembers = $contactsList->map(function (Member $related) use ($member, $memberTypeName): array {
                /** @var MembershipType $relatedType */
                $relatedType = $related->membership_type;

                return [
                    'id' => $related->pivot->id ?? $related->id,
                    'relatable_id' => $member->id,
                    'relatable_type' => 'Member',
                    'relatable_name' => $member->name,
                    'relatable_membership_type' => $memberTypeName,
                    'related_id' => $related->id,
                    'related_type' => 'Member',
                    'related_name' => $related->name,
                    'related_membership_type' => $relatedType->label(),
                ];
            })->all();
        }
        if ($member->relationLoaded('organisations')) {
            /** @var \Illuminate\Support\Collection<int, Member> $orgsList */
            $orgsList = $member->organisations;
            $parentMembers = $orgsList->map(function (Member $related) use ($member, $memberTypeName): array {
                /** @var MembershipType $relatedType */
                $relatedType = $related->membership_type;

                return [
                    'id' => $related->pivot->id ?? $related->id,
                    'relatable_id' => $member->id,
                    'relatable_type' => 'Member',
                    'relatable_name' => $member->name,
                    'relatable_membership_type' => $memberTypeName,
                    'related_id' => $related->id,
                    'related_type' => 'Member',
                    'related_name' => $related->name,
                    'related_membership_type' => $relatedType->label(),
                ];
            })->all();
        }

        return new self(
            id: $member->id,
            name: $member->name,
            membership_type: $memberType->label(),
            is_active: $member->is_active,
            description: $member->description ?? '',
            bookable: $member->bookable,
            location_type: $member->location_type,
            locale: $member->locale,
            day_cost: $member->formatMoneyCost('day_cost'),
            hour_cost: $member->formatMoneyCost('hour_cost'),
            distance_cost: $member->formatMoneyCost('distance_cost'),
            flat_rate_cost: $member->formatMoneyCost('flat_rate_cost'),
            membership_id: $member->id,
            lawful_basis_type_id: $member->lawful_basis_type_id,
            lawful_basis_type_name: $member->relationLoaded('lawfulBasisType')
                ? $member->lawfulBasisType?->name
                : null,
            sale_tax_class_id: $member->sale_tax_class_id,
            sale_tax_class_name: $member->relationLoaded('saleTaxClass')
                ? $member->saleTaxClass?->name
                : null,
            purchase_tax_class_id: $member->purchase_tax_class_id,
            purchase_tax_class_name: $member->relationLoaded('purchaseTaxClass')
                ? $member->purchaseTaxClass?->name
                : null,
            tag_list: $member->getAttribute('tag_list') ?? [],
            custom_fields: $member->relationLoaded('customFieldValues') ? $member->custom_fields : [],
            icon_exists: $member->icon_url !== null,
            mapping_id: $member->mapping_id,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            membership: $membershipData,
            primary_address: $primaryAddress,
            icon: $member->icon_url ? [
                'url' => $member->icon_url,
                'thumb_url' => $member->icon_thumb_url,
            ] : null,
            identity: null,
            addresses: $addresses,
            emails: $member->relationLoaded('emails')
                ? $member->emails->map(fn ($e): array => EmailData::fromModel($e)->toArray())->all()
                : [],
            phones: $member->relationLoaded('phones')
                ? $member->phones->map(fn ($p): array => PhoneData::fromModel($p)->toArray())->all()
                : [],
            links: $member->relationLoaded('links')
                ? $member->links->map(fn ($l): array => LinkData::fromModel($l)->toArray())->all()
                : [],
            service_stock_levels: [],
            child_members: $childMembers,
            parent_members: $parentMembers,
        );
    }

    /**
     * Build the type-specific membership data matching CRMS format.
     *
     * @return array<string, mixed>
     */
    private static function buildMembershipData(Member $member): array
    {
        $base = ['id' => $member->id];

        /** @var MembershipType $type */
        $type = $member->membership_type;

        return match ($type) {
            MembershipType::Organisation, MembershipType::Venue => array_merge($base, [
                'number' => $member->account_number ?? '',
                'tax_class_id' => $member->sale_tax_class_id,
                'cash' => $member->is_cash,
                'on_stop' => $member->is_on_stop,
                'rating' => $member->rating,
                'owned_by' => $member->owned_by,
                'price_category_id' => $member->price_category_id,
                'discount_category_id' => $member->discount_category_id,
                'tax_number' => $member->tax_number ?? '',
                'peppol_id' => $member->peppol_id ?? '',
                'chamber_of_commerce_number' => $member->chamber_of_commerce_number,
                'global_location_number' => $member->global_location_number ?? '',
                'invoice_term' => $member->invoice_term_id,
                'invoice_term_length' => $member->invoice_term_length,
            ]),
            MembershipType::Contact => array_merge($base, [
                'title' => $member->title,
                'department' => $member->department,
            ]),
            MembershipType::User => $base,
        };
    }
}
