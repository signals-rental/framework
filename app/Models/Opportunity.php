<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of the event-sourced opportunity lifecycle.
 *
 * This model carries NO business logic — every mutation flows through a Verbs
 * event whose handle() method dual-writes this row. It exists purely so list
 * views, the API, reports, and the availability engine have zero event-sourcing
 * read penalty.
 *
 * @property int $id
 * @property int $state_id
 * @property OpportunityState $state
 * @property int $status
 * @property int $active_version_id
 * @property int $version_count
 * @property bool $has_alternatives
 * @property string|null $currency_code
 * @property string $exchange_rate
 * @property bool $exchange_rate_locked
 * @property bool $tax_locked
 * @property int $charge_total
 * @property int|null $deal_total
 * @property int $rental_charge_total
 * @property int $sale_charge_total
 * @property int $service_charge_total
 * @property int $sub_rental_charge_total
 * @property int $transit_charge_total
 * @property int $loss_damage_charge_total
 * @property int $charge_excluding_tax_total
 * @property int $charge_including_tax_total
 * @property int $tax_total
 * @property bool $prices_include_tax
 * @property bool $has_shortage
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $charge_starts_at
 * @property Carbon|null $charge_ends_at
 * @property Carbon|null $prep_starts_at
 * @property Carbon|null $prep_ends_at
 * @property Carbon|null $load_starts_at
 * @property Carbon|null $load_ends_at
 * @property Carbon|null $deliver_starts_at
 * @property Carbon|null $deliver_ends_at
 * @property Carbon|null $setup_starts_at
 * @property Carbon|null $setup_ends_at
 * @property Carbon|null $show_starts_at
 * @property Carbon|null $show_ends_at
 * @property Carbon|null $takedown_starts_at
 * @property Carbon|null $takedown_ends_at
 * @property Carbon|null $collect_starts_at
 * @property Carbon|null $collect_ends_at
 * @property Carbon|null $unload_starts_at
 * @property Carbon|null $unload_ends_at
 * @property Carbon|null $deprep_starts_at
 * @property Carbon|null $deprep_ends_at
 * @property Carbon|null $ordered_at
 * @property Carbon|null $quote_invalid_at
 * @property bool $use_chargeable_days
 * @property string|null $chargeable_days
 * @property bool $open_ended_rental
 * @property bool $customer_collecting
 * @property bool $customer_returning
 * @property string|null $delivery_instructions
 * @property string|null $collection_instructions
 * @property int|null $delivery_address_id
 * @property int|null $collection_address_id
 * @property int|null $source_opportunity_id
 * @property int|null $rating
 * @property bool $invoiced
 * @property-read bool $pricing_locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Opportunity extends Model implements HasSchema
{
    /** @use HasFactory<OpportunityFactory> */
    use FormatsMoney, HasAttachments, HasCustomFields, HasFactory, SoftDeletes;

    /**
     * The PK is application-assigned (allocated at event-fire time and baked
     * into the OpportunityCreated event), so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'subject',
        'description',
        'number',
        'reference',
        'external_description',
        'state',
        'status',
        'active_version_id',
        'version_count',
        'has_alternatives',
        'member_id',
        'venue_id',
        'store_id',
        'owned_by',
        'starts_at',
        'ends_at',
        'charge_starts_at',
        'charge_ends_at',
        'prep_starts_at',
        'prep_ends_at',
        'load_starts_at',
        'load_ends_at',
        'deliver_starts_at',
        'deliver_ends_at',
        'setup_starts_at',
        'setup_ends_at',
        'show_starts_at',
        'show_ends_at',
        'takedown_starts_at',
        'takedown_ends_at',
        'collect_starts_at',
        'collect_ends_at',
        'unload_starts_at',
        'unload_ends_at',
        'deprep_starts_at',
        'deprep_ends_at',
        'ordered_at',
        'quote_invalid_at',
        'use_chargeable_days',
        'chargeable_days',
        'open_ended_rental',
        'customer_collecting',
        'customer_returning',
        'delivery_instructions',
        'collection_instructions',
        'delivery_address_id',
        'collection_address_id',
        'source_opportunity_id',
        'rating',
        'currency_code',
        'exchange_rate',
        'exchange_rate_locked',
        'tax_locked',
        'charge_total',
        'deal_total',
        'rental_charge_total',
        'sale_charge_total',
        'service_charge_total',
        'sub_rental_charge_total',
        'transit_charge_total',
        'loss_damage_charge_total',
        'charge_excluding_tax_total',
        'charge_including_tax_total',
        'tax_total',
        'prices_include_tax',
        'invoiced',
        'has_shortage',
        'tag_list',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => OpportunityState::class,
            'active_version_id' => 'integer',
            'version_count' => 'integer',
            'has_alternatives' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'charge_starts_at' => 'datetime',
            'charge_ends_at' => 'datetime',
            'prep_starts_at' => 'datetime',
            'prep_ends_at' => 'datetime',
            'load_starts_at' => 'datetime',
            'load_ends_at' => 'datetime',
            'deliver_starts_at' => 'datetime',
            'deliver_ends_at' => 'datetime',
            'setup_starts_at' => 'datetime',
            'setup_ends_at' => 'datetime',
            'show_starts_at' => 'datetime',
            'show_ends_at' => 'datetime',
            'takedown_starts_at' => 'datetime',
            'takedown_ends_at' => 'datetime',
            'collect_starts_at' => 'datetime',
            'collect_ends_at' => 'datetime',
            'unload_starts_at' => 'datetime',
            'unload_ends_at' => 'datetime',
            'deprep_starts_at' => 'datetime',
            'deprep_ends_at' => 'datetime',
            'ordered_at' => 'datetime',
            'quote_invalid_at' => 'datetime',
            'use_chargeable_days' => 'boolean',
            'chargeable_days' => 'decimal:1',
            'open_ended_rental' => 'boolean',
            'customer_collecting' => 'boolean',
            'customer_returning' => 'boolean',
            'delivery_address_id' => 'integer',
            'collection_address_id' => 'integer',
            'source_opportunity_id' => 'integer',
            'rating' => 'integer',
            'exchange_rate' => 'decimal:10',
            'exchange_rate_locked' => 'boolean',
            'tax_locked' => 'boolean',
            'prices_include_tax' => 'boolean',
            'invoiced' => 'boolean',
            'has_shortage' => 'boolean',
            'tag_list' => 'array',
        ];
    }

    /**
     * Resolve the composite two-axis status from the persisted `state` + `status`
     * columns. `status` is a per-state integer, so it is only meaningful paired
     * with `state`.
     */
    public function statusEnum(): OpportunityStatus
    {
        return OpportunityStatus::fromStateAndStatus($this->state, $this->status);
    }

    /**
     * Whether the opportunity's pricing is frozen (RMS `pricing_locked`). Derived
     * from the FX and tax lock flags, both of which lock at quote → order
     * conversion: once either is set the stored totals are preserved rather than
     * re-derived. Read-only — there is no `pricing_locked` column.
     */
    public function pricingLocked(): bool
    {
        return (bool) $this->exchange_rate_locked || (bool) $this->tax_locked;
    }

    /**
     * Whether FX and/or tax locks are active on this opportunity.
     */
    public function hasLocks(): bool
    {
        return $this->pricingLocked();
    }

    /**
     * Whether line-item pricing fields are frozen in the editor — deal price
     * and/or FX/tax locks both impose a full pricing freeze.
     */
    public function pricingFrozen(): bool
    {
        return $this->deal_total !== null || $this->hasLocks();
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('subject')->label('Subject')->required()->searchable()->filterable()->sortable();
        $builder->string('number')->label('Number')->searchable()->filterable()->sortable();
        $builder->string('reference')->label('Reference')->searchable()->filterable()->sortable();
        $builder->enum('state')->label('State')->filterable()->sortable()->groupable();
        $builder->enum('status')->label('Status')->filterable()->sortable()->groupable();
        $builder->relation('member_id')->label('Member')
            ->relation('member', 'belongsTo', Member::class, 'name')
            ->filterable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->filterable();
        $builder->relation('owned_by')->label('Owner')
            ->relation('owner', 'belongsTo', Member::class, 'name')
            ->filterable();
        $builder->relation('delivery_address_id')->label('Delivery Address')
            ->relation('deliveryAddress', 'belongsTo', Address::class, 'name')
            ->filterable();
        $builder->relation('collection_address_id')->label('Collection Address')
            ->relation('collectionAddress', 'belongsTo', Address::class, 'name')
            ->filterable();
        $builder->datetime('starts_at')->label('Starts')->sortable()->filterable();
        $builder->datetime('ends_at')->label('Ends')->sortable()->filterable();
        $builder->datetime('charge_starts_at')->label('Charge Starts')->sortable()->filterable();
        $builder->datetime('charge_ends_at')->label('Charge Ends')->sortable()->filterable();
        $builder->datetime('deliver_starts_at')->label('Delivery Starts')->sortable()->filterable();
        $builder->datetime('deliver_ends_at')->label('Delivery Ends')->sortable()->filterable();
        $builder->datetime('collect_starts_at')->label('Collection Starts')->sortable()->filterable();
        $builder->datetime('collect_ends_at')->label('Collection Ends')->sortable()->filterable();
        $builder->datetime('ordered_at')->label('Ordered')->sortable()->filterable();
        $builder->datetime('quote_invalid_at')->label('Quote Expires')->sortable()->filterable();
        $builder->integer('charge_total')->label('Charge Total')->sortable();
        $builder->boolean('invoiced')->label('Invoiced')->filterable()->sortable()->groupable();
        $builder->boolean('use_chargeable_days')->label('Uses Chargeable Days')->filterable()->groupable();
        $builder->boolean('open_ended_rental')->label('Open-Ended Rental')->filterable()->groupable();
        $builder->boolean('customer_collecting')->label('Customer Collecting')->filterable()->groupable();
        $builder->boolean('customer_returning')->label('Customer Returning')->filterable()->groupable();
        $builder->boolean('has_shortage')->label('Has Shortage')->filterable()->sortable()->groupable();
        $builder->json('tag_list')->label('Tags')->searchable()->filterable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'venue_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'owned_by');
    }

    /**
     * The opportunity this one was cloned from (clone lineage), or null when it
     * was created directly rather than cloned.
     *
     * @return BelongsTo<Opportunity, $this>
     */
    public function sourceOpportunity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_opportunity_id');
    }

    /**
     * The delivery address for this opportunity (one of the member's addresses),
     * or null when none is set (C-data-2).
     *
     * @return BelongsTo<Address, $this>
     */
    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    /**
     * The collection address for this opportunity (one of the member's
     * addresses), or null when none is set (C-data-2).
     *
     * @return BelongsTo<Address, $this>
     */
    public function collectionAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'collection_address_id');
    }

    /**
     * Line items belonging to this opportunity, in display order.
     *
     * VERSION SCOPING (opportunity-lifecycle.md §8.7): once the opportunity carries
     * quote versions this relation is scoped to the ACTIVE version's items only, so
     * totals, demands, and API reads all follow the active version. Non-versioned
     * (legacy) opportunities have `active_version_id = 0` and their items carry a
     * NULL `version_id`, so the scope is a no-op and behaviour is identical to
     * before versioning.
     *
     * The scope is expressed as a row-correlated constraint (rather than a
     * per-instance `where` on `$this->active_version_id`) so it holds under BATCHED
     * EAGER LOADING too. Eager loads build the relation on a constraint-less
     * `newInstance()` where `active_version_id` is null, which would otherwise skip
     * a dynamic guard and leak every version's items. The correlated subquery ties
     * each item to its own opportunity's `active_version_id`, working identically
     * for lazy access, `with()`/`load()`/`loadMissing()`/`fresh()`, and
     * `withCount()`, on both SQLite and PostgreSQL.
     *
     * @return HasMany<OpportunityItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class, 'opportunity_id')
            ->orderBy('path')
            ->where(function ($query): void {
                $query->whereNull('opportunity_items.version_id')
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('opportunities as opp_active_version')
                            ->whereColumn('opp_active_version.id', 'opportunity_items.opportunity_id')
                            ->whereColumn('opp_active_version.active_version_id', 'opportunity_items.version_id');
                    });
            });
    }

    /**
     * ALL line items across every version of this opportunity, unscoped by the
     * active version. Used by the version-diff path, which compares two specific
     * versions' items directly.
     *
     * @return HasMany<OpportunityItem, $this>
     */
    public function allItems(): HasMany
    {
        return $this->hasMany(OpportunityItem::class, 'opportunity_id')->orderBy('path');
    }

    /**
     * Quote versions belonging to this opportunity, oldest first.
     *
     * @return HasMany<OpportunityVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(OpportunityVersion::class, 'opportunity_id')->orderBy('version_number');
    }

    /**
     * The currently-active quote version (null when the opportunity has none).
     *
     * @return BelongsTo<OpportunityVersion, $this>
     */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(OpportunityVersion::class, 'active_version_id');
    }

    /**
     * Ad-hoc costs (delivery, labour, surcharges, etc.) belonging to this
     * opportunity, in display order.
     *
     * @return HasMany<OpportunityCost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(OpportunityCost::class, 'opportunity_id')->orderBy('sort_order');
    }

    /**
     * Members associated with this opportunity, each in a named role. Plain,
     * NON-event-sourced CRM associations (mirrors {@see Activity::participants()}).
     *
     * @return HasMany<OpportunityParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(OpportunityParticipant::class, 'opportunity_id')->orderBy('id');
    }

    /**
     * CRM activities (calls, emails, tasks, notes) regarding this opportunity.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'regarding');
    }

    /**
     * Scope to opportunities of a given document-type state (Draft / Quotation /
     * Order). Drives the index page's state-filter chips.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeOfState(Builder $query, OpportunityState $state): Builder
    {
        return $query->where('state', $state);
    }

    /**
     * Scope to opportunities associated with a member in any CRM role: as the
     * customer (`member_id`), the owning salesperson (`owned_by`), or a named
     * participant (`opportunity_participants`). The customer association is the
     * primary one; owner and participant rows are included so the member's
     * related-opportunities list is the full CRM picture. The venue role is
     * deliberately excluded — a venue is a location, not the member's own
     * involvement in the deal.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where(function (Builder $query) use ($memberId): void {
            $query->where('member_id', $memberId)
                ->orWhere('owned_by', $memberId)
                ->orWhereHas('participants', function (Builder $query) use ($memberId): void {
                    $query->where('member_id', $memberId);
                });
        });
    }

    /**
     * Scope to archived (soft-deleted) opportunities only.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include archived (soft-deleted) opportunities.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withTrashed();
    }

    /**
     * Scope to opportunities currently flagged with an availability shortage. Drives
     * the index page's "With shortages" toggle and the dashboard widget deep-link.
     *
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    public function scopeWithShortage(Builder $query): Builder
    {
        return $query->where('has_shortage', true);
    }

    /**
     * Format money columns in the opportunity's own snapshotted currency, falling
     * back to the company base currency when none is set.
     */
    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        return is_string($code) && $code !== '' ? $code : $this->baseFormattingCurrency();
    }
}
