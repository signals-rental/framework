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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $currency_code
 * @property string $exchange_rate
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
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $charge_starts_at
 * @property Carbon|null $charge_ends_at
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
        'member_id',
        'venue_id',
        'store_id',
        'owned_by',
        'starts_at',
        'ends_at',
        'charge_starts_at',
        'charge_ends_at',
        'currency_code',
        'exchange_rate',
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
        'tag_list',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => OpportunityState::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'charge_starts_at' => 'datetime',
            'charge_ends_at' => 'datetime',
            'exchange_rate' => 'decimal:10',
            'prices_include_tax' => 'boolean',
            'invoiced' => 'boolean',
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

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('subject')->label('Subject')->required()->searchable()->filterable()->sortable();
        $builder->string('number')->label('Number')->searchable()->filterable()->sortable();
        $builder->string('reference')->label('Reference')->searchable()->filterable()->sortable();
        $builder->integer('state')->label('State')->filterable()->sortable()->groupable();
        $builder->integer('status')->label('Status')->filterable()->sortable()->groupable();
        $builder->relation('member_id')->label('Member')
            ->relation('member', 'belongsTo', Member::class, 'name')
            ->filterable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->filterable();
        $builder->relation('owned_by')->label('Owner')
            ->relation('owner', 'belongsTo', Member::class, 'name')
            ->filterable();
        $builder->datetime('starts_at')->label('Starts')->sortable()->filterable();
        $builder->datetime('ends_at')->label('Ends')->sortable()->filterable();
        $builder->integer('charge_total')->label('Charge Total')->sortable();
        $builder->boolean('invoiced')->label('Invoiced')->filterable()->sortable()->groupable();
        $builder->json('tag_list')->label('Tags')->searchable();
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
     * Line items belonging to this opportunity, in display order.
     *
     * @return HasMany<OpportunityItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OpportunityItem::class, 'opportunity_id')->orderBy('sort_order');
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
}
