<?php

namespace App\Models;

use App\Services\Availability\RecalculationPipeline;
use Database\Factories\AvailabilityDailySummaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A rolled-up daily availability summary for one product/store/calendar-day.
 *
 * Derived from the intra-day `availability_snapshots` by the
 * {@see RecalculationPipeline}; the hot read path for calendar and month-grid
 * queries. `min_available` / `max_available` bound the day's availability across
 * every slot it contained, and `has_shortage` flags any dip below zero.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property Carbon $date
 * @property int $min_available
 * @property int $max_available
 * @property int $pending_checkin_quantity
 * @property bool $has_shortage
 * @property Carbon $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AvailabilityDailySummary extends Model
{
    /** @use HasFactory<AvailabilityDailySummaryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'store_id',
        'date',
        'min_available',
        'max_available',
        'pending_checkin_quantity',
        'has_shortage',
        'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'min_available' => 'integer',
            'max_available' => 'integer',
            'pending_checkin_quantity' => 'integer',
            'has_shortage' => 'boolean',
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
     * @param  Builder<AvailabilityDailySummary>  $query
     * @return Builder<AvailabilityDailySummary>
     */
    public function scopeForProductStore(Builder $query, int $productId, int $storeId): Builder
    {
        return $query->where('product_id', $productId)->where('store_id', $storeId);
    }

    /**
     * Scope to summaries whose date falls within the inclusive `[from, to]`
     * calendar window.
     *
     * @param  Builder<AvailabilityDailySummary>  $query
     * @return Builder<AvailabilityDailySummary>
     */
    public function scopeInDateRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());
    }

    /**
     * Scope to summaries representing a shortage on the day.
     *
     * @param  Builder<AvailabilityDailySummary>  $query
     * @return Builder<AvailabilityDailySummary>
     */
    public function scopeShortage(Builder $query): Builder
    {
        return $query->where('has_shortage', true);
    }
}
