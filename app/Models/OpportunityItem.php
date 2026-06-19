<?php

namespace App\Models;

use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\Traits\FormatsMoney;
use Database\Factories\OpportunityItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of an event-sourced opportunity line item.
 *
 * Carries NO business logic — every mutation flows through a Verbs item event
 * (M3) whose handle() method dual-writes this row. It exists purely so list
 * views, the API, reports, and the availability engine read line items with zero
 * event-sourcing penalty.
 *
 * The PK is application-assigned (allocated at event-fire time via
 * SequenceAllocator and baked into the genesis event), so Eloquent must not
 * auto-increment it. Money columns are integer minor units; `custom_fields` is
 * an inline JSON map (per the RMS line-item schema), distinct from the entity
 * EAV custom-field system used on opportunities.
 *
 * @property int $id
 * @property int $state_id
 * @property int $opportunity_id
 * @property int|null $version_id
 * @property int|null $item_id
 * @property string|null $item_type
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property int $unit_price
 * @property ChargePeriod $charge_period
 * @property int $total
 * @property string|null $currency_code
 * @property string|null $discount_percent
 * @property string|null $tax_rate
 * @property LineItemTransactionType $transaction_type
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string $allocated_quantity
 * @property string $dispatched_quantity
 * @property string $returned_quantity
 * @property int|null $dispatch_store_id
 * @property int|null $return_store_id
 * @property int $sort_order
 * @property bool $is_optional
 * @property array<string, mixed>|null $custom_fields
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunityItem extends Model
{
    /** @use HasFactory<OpportunityItemFactory> */
    use FormatsMoney, HasFactory;

    /**
     * The PK is application-assigned (allocated at event-fire time and baked into
     * the ItemAddedToOpportunity event), so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'opportunity_id',
        'version_id',
        'item_id',
        'item_type',
        'name',
        'description',
        'quantity',
        'unit_price',
        'charge_period',
        'total',
        'currency_code',
        'discount_percent',
        'tax_rate',
        'transaction_type',
        'starts_at',
        'ends_at',
        'allocated_quantity',
        'dispatched_quantity',
        'returned_quantity',
        'dispatch_store_id',
        'return_store_id',
        'sort_order',
        'is_optional',
        'custom_fields',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'charge_period' => ChargePeriod::class,
            'total' => 'integer',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'transaction_type' => LineItemTransactionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'allocated_quantity' => 'decimal:2',
            'dispatched_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'dispatch_store_id' => 'integer',
            'return_store_id' => 'integer',
            'sort_order' => 'integer',
            'is_optional' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * @return HasMany<OpportunityItemAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(OpportunityItemAsset::class, 'opportunity_item_id');
    }

    /**
     * The store this line dispatches from when it overrides the opportunity's
     * primary store (null = inherit the opportunity's store).
     *
     * @return BelongsTo<Store, $this>
     */
    public function dispatchStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'dispatch_store_id');
    }

    /**
     * The store this line is expected back at when it differs from where it went
     * out (null = inherit the opportunity's store). Forward-looking for M5.
     *
     * @return BelongsTo<Store, $this>
     */
    public function returnStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'return_store_id');
    }
}
