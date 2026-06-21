<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Database\Factories\OpportunityItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
 * @property int|null $section_id
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
class OpportunityItem extends Model implements HasSchema
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
        'section_id',
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
            'section_id' => 'integer',
            'quantity' => 'decimal:2',
            'unit_price' => 'integer',
            'charge_period' => ChargePeriod::class,
            'total' => 'integer',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'transaction_type' => LineItemTransactionType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'dispatched_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'dispatch_store_id' => 'integer',
            'return_store_id' => 'integer',
            'sort_order' => 'integer',
            'is_optional' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('opportunity_id')->label('Opportunity')
            ->relation('opportunity', 'belongsTo', Opportunity::class, 'subject')
            ->filterable();
        $builder->relation('section_id')->label('Section')
            ->relation('section', 'belongsTo', OpportunitySection::class, 'name')
            ->filterable();
        $builder->integer('item_id')->label('Item')->filterable();
        $builder->string('item_type')->label('Item Type')->filterable()->groupable();
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->decimal('quantity')->label('Quantity')->filterable()->sortable();
        $builder->integer('unit_price')->label('Unit Price')->sortable();
        $builder->enum('charge_period')->label('Charge Period')->filterable()->groupable();
        $builder->integer('total')->label('Total')->sortable();
        $builder->enum('transaction_type')->label('Transaction Type')->filterable()->groupable();
        $builder->datetime('starts_at')->label('Starts')->filterable()->sortable();
        $builder->datetime('ends_at')->label('Ends')->filterable()->sortable();
        $builder->boolean('is_optional')->label('Optional')->filterable()->sortable();
        $builder->integer('sort_order')->label('Sort Order')->sortable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }

    /**
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    /**
     * The custom grouping (section) this line is assigned to, if any. The link
     * is a plain, NON-event-sourced column: it is written only by plain actions,
     * never by a Verbs event/apply()/handle(), so a replay never disturbs it.
     * Null = the line falls back to automatic product-group grouping in the UI.
     *
     * @return BelongsTo<OpportunitySection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(OpportunitySection::class, 'section_id');
    }

    /**
     * @return HasMany<OpportunityItemAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(OpportunityItemAsset::class, 'opportunity_item_id');
    }

    /**
     * The catalogue entity this line references (typically a {@see Product}),
     * resolved polymorphically from `item_type`/`item_id`. Eager-load this to
     * avoid per-line lookups when deriving stock semantics.
     *
     * @return MorphTo<Model, $this>
     */
    public function item(): MorphTo
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    /**
     * Whether this line references a catalogue {@see Product} — it has a concrete
     * `item_id` and an `item_type` that resolves to Product (the model FQN or the
     * short `product` morph alias). Non-product lines (services, ad-hoc) generate no
     * demand and are never priced by the rate engine.
     */
    public function isProductBacked(): bool
    {
        if ($this->item_id === null || $this->item_type === null) {
            return false;
        }

        return $this->item_type === Product::class || strtolower($this->item_type) === 'product';
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

    /**
     * Format money columns in the line's own currency, falling back to the parent
     * opportunity's currency (when already loaded) and finally the company base
     * currency.
     */
    protected function moneyFormattingCurrency(): string
    {
        $code = $this->currency_code;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        $parentCode = $this->relationLoaded('opportunity') ? $this->opportunity?->currency_code : null;

        return is_string($parentCode) && $parentCode !== '' ? $parentCode : $this->baseFormattingCurrency();
    }
}
