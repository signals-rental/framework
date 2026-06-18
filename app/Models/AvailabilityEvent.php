<?php

namespace App\Models;

use App\Enums\AvailabilityEventType;
use Database\Factories\AvailabilityEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable, append-only availability audit record.
 *
 * Records demand lifecycle changes and recalculations (and, in later
 * milestones, stock and shortage events). Has only a `created_at` timestamp —
 * rows are never updated.
 *
 * @property int $id
 * @property AvailabilityEventType $event_type
 * @property int $product_id
 * @property int $store_id
 * @property int|null $demand_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property array<string, mixed> $payload
 * @property Carbon|null $created_at
 */
class AvailabilityEvent extends Model
{
    /** @use HasFactory<AvailabilityEventFactory> */
    use HasFactory;

    /** Append-only: there is no `updated_at`. */
    public const ?string UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'event_type',
        'product_id',
        'store_id',
        'demand_id',
        'source_type',
        'source_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => AvailabilityEventType::class,
            'payload' => 'array',
        ];
    }

    /**
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
     * The demand this event concerns, if any. Demands may be hard-deleted, so
     * this relation can resolve to null even when `demand_id` is set.
     *
     * @return BelongsTo<Demand, $this>
     */
    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }

    /**
     * Scope to events of a given type.
     *
     * @param  Builder<AvailabilityEvent>  $query
     * @return Builder<AvailabilityEvent>
     */
    public function scopeOfType(Builder $query, AvailabilityEventType $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to events for a single product/store.
     *
     * @param  Builder<AvailabilityEvent>  $query
     * @return Builder<AvailabilityEvent>
     */
    public function scopeForProductStore(Builder $query, int $productId, int $storeId): Builder
    {
        return $query->where('product_id', $productId)->where('store_id', $storeId);
    }
}
