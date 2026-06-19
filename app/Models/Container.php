<?php

namespace App\Models;

use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A serialised (or temporary) housing that groups other serialised assets into a
 * single dispatchable unit (serialised-containers.md).
 *
 * Containers are plain Eloquent and NOT event-sourced — only opportunities are.
 * The operational overlay on top of the CRMS-compat
 * `stock_levels.container_stock_level_id` columns.
 *
 * For the M5-3b availability subset only the `open` / `sealed` statuses and the
 * pack/unpack write surface are exercised; seal/dissolve/dispatch/return and the
 * scanning lifecycle are Phase-4.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $serialised_item_id
 * @property int|null $product_id
 * @property int|null $parent_container_id
 * @property int|null $previous_container_id
 * @property bool $is_temporary
 * @property string|null $barcode
 * @property int|null $store_id
 * @property ContainerScanMode $scan_mode
 * @property ContainerStatus $status
 * @property Carbon|null $sealed_at
 * @property int|null $sealed_by_user_id
 * @property Carbon|null $unsealed_at
 * @property int|null $unsealed_by_user_id
 * @property Carbon|null $dissolved_at
 * @property int|null $dissolved_by_user_id
 * @property string|null $dissolved_reason
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $returned_at
 * @property int|null $opportunity_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'name',
        'serialised_item_id',
        'product_id',
        'parent_container_id',
        'previous_container_id',
        'is_temporary',
        'barcode',
        'store_id',
        'scan_mode',
        'status',
        'sealed_at',
        'sealed_by_user_id',
        'unsealed_at',
        'unsealed_by_user_id',
        'dissolved_at',
        'dissolved_by_user_id',
        'dissolved_reason',
        'dispatched_at',
        'returned_at',
        'opportunity_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_temporary' => 'boolean',
            'scan_mode' => ContainerScanMode::class,
            'status' => ContainerStatus::class,
            'sealed_at' => 'datetime',
            'unsealed_at' => 'datetime',
            'dissolved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * Generate UUIDs only for the public `uuid` column, not the integer PK.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The serialised housing asset (null for temporary containers).
     *
     * @return BelongsTo<StockLevel, $this>
     */
    public function serialisedItem(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'serialised_item_id');
    }

    /**
     * The containerable product backing this container (null for temporary).
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * The container this one is nested inside (Phase-4 nesting).
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_container_id');
    }

    /**
     * Containers nested inside this one.
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_container_id');
    }

    /**
     * All membership rows ever recorded for this container (active + closed).
     *
     * @return HasMany<ContainerItem, $this>
     */
    public function containerItems(): HasMany
    {
        return $this->hasMany(ContainerItem::class);
    }

    /**
     * The currently-packed (active) membership rows.
     *
     * @return HasMany<ContainerItem, $this>
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(ContainerItem::class)->whereNull('unpacked_at');
    }

    /**
     * The container's availability mode, resolved from the backing product.
     * Temporary containers (no product) default to transport — they hold nothing
     * back from availability.
     */
    public function availabilityMode(): ContainerAvailabilityMode
    {
        $mode = $this->product?->container_availability_mode;

        if ($mode instanceof ContainerAvailabilityMode) {
            return $mode;
        }

        return ContainerAvailabilityMode::Transport;
    }

    /**
     * Whether this container removes its contents from individual availability
     * (kit always; hybrid for its fixed-binding members — decided per item).
     */
    public function holdsContentsFromAvailability(): bool
    {
        return $this->availabilityMode() !== ContainerAvailabilityMode::Transport;
    }

    /**
     * Scope to active (non-dissolved) containers.
     *
     * @param  Builder<Container>  $query
     * @return Builder<Container>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', ContainerStatus::Dissolved->value);
    }
}
