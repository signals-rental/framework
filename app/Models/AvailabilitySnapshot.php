<?php

namespace App\Models;

use App\Services\Availability\RecalculationPipeline;
use Database\Factories\AvailabilitySnapshotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pre-calculated availability bucket for one product/store/time-slot.
 *
 * Derived from the authoritative `demands` table by the
 * {@see RecalculationPipeline}; the hot read path for
 * range/grid availability queries. `available` may be negative (a shortage).
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property Carbon $slot_start
 * @property int $total_stock
 * @property int $total_demanded
 * @property int $available
 * @property array<string, int> $demand_breakdown
 * @property int $pending_checkin_quantity
 * @property Carbon $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AvailabilitySnapshot extends Model
{
    /** @use HasFactory<AvailabilitySnapshotFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'store_id',
        'slot_start',
        'total_stock',
        'total_demanded',
        'available',
        'demand_breakdown',
        'pending_checkin_quantity',
        'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_start' => 'datetime',
            'total_stock' => 'integer',
            'total_demanded' => 'integer',
            'available' => 'integer',
            'demand_breakdown' => 'array',
            'pending_checkin_quantity' => 'integer',
            'calculated_at' => 'datetime',
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
     * Scope to a single product/store.
     *
     * @param  Builder<AvailabilitySnapshot>  $query
     * @return Builder<AvailabilitySnapshot>
     */
    public function scopeForProductStore(Builder $query, int $productId, int $storeId): Builder
    {
        return $query->where('product_id', $productId)->where('store_id', $storeId);
    }

    /**
     * Scope to slots whose start falls within the half-open `[from, to)` window.
     *
     * @param  Builder<AvailabilitySnapshot>  $query
     * @return Builder<AvailabilitySnapshot>
     */
    public function scopeInWindow(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('slot_start', '>=', $from)->where('slot_start', '<', $to);
    }

    /**
     * Scope to snapshots representing a shortage (negative availability).
     *
     * @param  Builder<AvailabilitySnapshot>  $query
     * @return Builder<AvailabilitySnapshot>
     */
    public function scopeShortage(Builder $query): Builder
    {
        return $query->where('available', '<', 0);
    }
}
