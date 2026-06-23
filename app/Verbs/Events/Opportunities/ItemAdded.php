<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for an opportunity line item. Creates the item state, projects
 * the `opportunity_items` row, prices it via the rate + tax engines, rolls the
 * totals up onto the parent opportunity, and syncs the availability demand.
 *
 * `opportunity_item_id` is the application-allocated small projection PK,
 * allocated by the AddOpportunityItem action via {@see SequenceAllocator} and
 * baked into the payload so replay reproduces identical ids (replay-stable).
 *
 * The demand sync runs only when NOT replaying (see {@see PricesOpportunityItems});
 * the projection dual-write, totals recompute, and audit bridge all run on replay.
 */
class ItemAdded extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        public int $opportunity_item_id,
        #[StateId(OpportunityItemState::class)]
        public ?int $state_id = null,
        public int $opportunity_id = 0,
        public ?int $version_id = null,
        public ?int $itemable_id = null,
        public ?string $itemable_type = null,
        public string $item_type = 'product',
        public string $path = '',
        public ?int $revenue_group_id = null,
        public string $name = '',
        public ?string $description = null,
        public string $quantity = '1',
        public int $transaction_type = 0,
        public int $charge_period = 1,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public bool $is_optional = false,
        public ?int $manual_unit_price = null,
        public ?string $discount_percent = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
        public ?string $notes = null,
    ) {}

    public function apply(OpportunityItemState $state): void
    {
        $state->opportunity_item_id = $this->opportunity_item_id;
        $state->opportunity_id = $this->opportunity_id;
        $state->version_id = $this->version_id;
        $state->itemable_id = $this->itemable_id;
        $state->itemable_type = $this->itemable_type;
        $state->item_type = $this->item_type;
        $state->path = $this->path;
        $state->revenue_group_id = $this->revenue_group_id;
        $state->name = $this->name;
        $state->description = $this->description;
        $state->quantity = $this->quantity;
        $state->transaction_type = $this->transaction_type;
        $state->charge_period = $this->charge_period;
        $state->starts_at = $this->starts_at !== null ? CarbonImmutable::parse($this->starts_at) : null;
        $state->ends_at = $this->ends_at !== null ? CarbonImmutable::parse($this->ends_at) : null;
        $state->is_optional = $this->is_optional;
        $state->manual_unit_price = $this->manual_unit_price;
        $state->unit_price = $this->manual_unit_price ?? 0;
        $state->discount_percent = $this->discount_percent;
        $state->custom_fields = $this->custom_fields;
        $state->notes = $this->notes;
        $state->is_removed = false;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $opportunity = Opportunity::query()->whereKey($state->opportunity_id)->first();

        $item = OpportunityItem::query()->updateOrCreate(
            ['id' => $state->opportunity_item_id],
            [
                'state_id' => $state->id,
                'opportunity_id' => $state->opportunity_id,
                'version_id' => $state->version_id,
                'itemable_id' => $state->itemable_id,
                'itemable_type' => $state->itemable_type,
                'item_type' => $state->item_type,
                'path' => $state->path,
                'revenue_group_id' => $state->revenue_group_id,
                'name' => $state->name,
                'description' => $state->description,
                'quantity' => $state->quantity,
                'unit_price' => $state->unit_price,
                'charge_period' => $state->charge_period,
                'total' => 0,
                'currency_code' => $opportunity?->currency_code,
                'discount_percent' => $state->discount_percent,
                'transaction_type' => $state->transaction_type,
                'starts_at' => $state->starts_at,
                'ends_at' => $state->ends_at,
                'is_optional' => $state->is_optional,
                'custom_fields' => $state->custom_fields,
                'notes' => $state->notes,
            ],
        );

        $role = OpportunityItemType::from($state->item_type);

        if ($role->isPriceable()) {
            $this->repriceAndRollUp($item, $state->manual_unit_price);
        }

        if ($role->generatesDemand()) {
            $this->syncDemand($item);
        }

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_added',
                newValues: $this->itemSnapshot($item),
                oldValues: null,
            );
        }
    }
}
