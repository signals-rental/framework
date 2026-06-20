<?php

namespace App\Data\Opportunities;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\ReleasePoint;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

/**
 * API/serialisation representation of an opportunity.
 *
 * Money totals are emitted as decimal strings (RMS format). Dates are ISO-8601
 * UTC. The two-axis state model is exposed both as raw RMS integers and as
 * human-readable labels, alongside the availability demand phase the current
 * status implies (the {@see DemandPhase} value). Relationships are
 * lazy — only present when eager-loaded.
 */
class OpportunityData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $subject,
        public ?string $number,
        public ?string $reference,
        public ?string $description,
        public ?string $external_description,
        public int $state,
        public string $state_label,
        public int $status,
        public string $status_label,
        public string $availability_phase,
        public int $active_version_id,
        public int $version_count,
        public bool $has_alternatives,
        public ?int $member_id,
        public ?int $venue_id,
        public ?int $store_id,
        public ?int $owned_by,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?string $charge_starts_at,
        public ?string $charge_ends_at,
        public ?string $currency_code,
        public string $exchange_rate,
        public bool $exchange_rate_locked,
        public bool $tax_locked,
        public string $charge_total,
        public ?string $deal_total,
        public string $rental_charge_total,
        public string $sale_charge_total,
        public string $service_charge_total,
        public string $sub_rental_charge_total,
        public string $transit_charge_total,
        public string $loss_damage_charge_total,
        public string $charge_excluding_tax_total,
        public string $charge_including_tax_total,
        public string $tax_total,
        public bool $prices_include_tax,
        public bool $invoiced,
        public bool $has_shortage,
        /** @var array<int, string> */
        public array $tag_list,
        public object $custom_fields,
        public string $created_at,
        public string $updated_at,
        public Lazy|EntityReferenceData|null $member = null,
        public Lazy|EntityReferenceData|null $venue = null,
        public Lazy|EntityReferenceData|null $store = null,
        public Lazy|EntityReferenceData|null $owner = null,
        /** @var Lazy|array<int, OpportunityItemData> */
        public Lazy|array $items = [],
        /** @var Lazy|array<int, OpportunityCostData> */
        public Lazy|array $costs = [],
        /** @var Lazy|array<int, OpportunityVersionData> */
        public Lazy|array $versions = [],
    ) {}

    public static function fromModel(Opportunity $opportunity): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $opportunity->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $opportunity->updated_at;

        $status = $opportunity->statusEnum();

        // Report the demand phase under the configured release point so the
        // serialised `availability_phase` matches the demand actually written.
        // This is a read-path settings read (not a replay path), so it is safe.
        $releasePoint = ReleasePoint::tryFrom(
            (string) settings('availability.release_point', ReleasePoint::default()->value),
        ) ?? ReleasePoint::default();

        return new self(
            id: $opportunity->id,
            subject: $opportunity->subject,
            number: $opportunity->number,
            reference: $opportunity->reference,
            description: $opportunity->description,
            external_description: $opportunity->external_description,
            state: $opportunity->state->value,
            state_label: $opportunity->state->label(),
            status: $opportunity->status,
            status_label: $status->label(),
            availability_phase: $status->phase($releasePoint)->value,
            active_version_id: $opportunity->active_version_id,
            version_count: $opportunity->version_count,
            has_alternatives: $opportunity->has_alternatives,
            member_id: $opportunity->member_id,
            venue_id: $opportunity->venue_id,
            store_id: $opportunity->store_id,
            owned_by: $opportunity->owned_by,
            starts_at: self::formatNullableTimestamp($opportunity->starts_at),
            ends_at: self::formatNullableTimestamp($opportunity->ends_at),
            charge_starts_at: self::formatNullableTimestamp($opportunity->charge_starts_at),
            charge_ends_at: self::formatNullableTimestamp($opportunity->charge_ends_at),
            currency_code: $opportunity->currency_code,
            exchange_rate: (string) $opportunity->exchange_rate,
            exchange_rate_locked: $opportunity->exchange_rate_locked,
            tax_locked: $opportunity->tax_locked,
            charge_total: $opportunity->formatMoneyCost('charge_total'),
            deal_total: $opportunity->deal_total !== null ? $opportunity->formatMoneyCost('deal_total') : null,
            rental_charge_total: $opportunity->formatMoneyCost('rental_charge_total'),
            sale_charge_total: $opportunity->formatMoneyCost('sale_charge_total'),
            service_charge_total: $opportunity->formatMoneyCost('service_charge_total'),
            sub_rental_charge_total: $opportunity->formatMoneyCost('sub_rental_charge_total'),
            transit_charge_total: $opportunity->formatMoneyCost('transit_charge_total'),
            loss_damage_charge_total: $opportunity->formatMoneyCost('loss_damage_charge_total'),
            charge_excluding_tax_total: $opportunity->formatMoneyCost('charge_excluding_tax_total'),
            charge_including_tax_total: $opportunity->formatMoneyCost('charge_including_tax_total'),
            tax_total: $opportunity->formatMoneyCost('tax_total'),
            prices_include_tax: $opportunity->prices_include_tax,
            invoiced: $opportunity->invoiced,
            has_shortage: $opportunity->has_shortage,
            tag_list: $opportunity->tag_list ?? [],
            custom_fields: (object) ($opportunity->relationLoaded('customFieldValues') ? $opportunity->custom_fields : []),
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            member: Lazy::whenLoaded('member', $opportunity, fn (): ?EntityReferenceData => self::reference($opportunity->member)),
            venue: Lazy::whenLoaded('venue', $opportunity, fn (): ?EntityReferenceData => self::reference($opportunity->venue)),
            store: Lazy::whenLoaded('store', $opportunity, fn (): ?EntityReferenceData => self::reference($opportunity->store)),
            owner: Lazy::whenLoaded('owner', $opportunity, fn (): ?EntityReferenceData => self::reference($opportunity->owner)),
            items: Lazy::whenLoaded(
                'items',
                $opportunity,
                fn (): array => $opportunity->items->map(
                    fn (OpportunityItem $item): OpportunityItemData => OpportunityItemData::fromModel($item)
                )->all(),
            ),
            costs: Lazy::whenLoaded(
                'costs',
                $opportunity,
                fn (): array => $opportunity->costs->map(
                    fn (OpportunityCost $cost): OpportunityCostData => OpportunityCostData::fromModel($cost)
                )->all(),
            ),
            versions: Lazy::whenLoaded(
                'versions',
                $opportunity,
                fn (): array => $opportunity->versions->map(
                    fn (OpportunityVersion $version): OpportunityVersionData => OpportunityVersionData::fromModel($version)
                )->all(),
            ),
        );
    }

    private static function reference(Member|Store|null $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        return EntityReferenceData::from(['id' => $model->id, 'name' => $model->name]);
    }

    private static function formatNullableTimestamp(?\DateTimeInterface $timestamp): ?string
    {
        return $timestamp !== null ? self::formatTimestamp($timestamp) : null;
    }

    /**
     * Convenience: the default open status for a given state value.
     */
    public static function defaultStatusFor(int $state): int
    {
        return OpportunityState::from($state)->defaultStatus()->statusValue();
    }
}
