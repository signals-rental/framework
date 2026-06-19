<?php

namespace App\Models;

use App\Enums\WaitlistMonitorStatus;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use Database\Factories\ShortageWaitlistMonitorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A durable watch on a (computed, transient) shortage
 * (shortage-resolution-sub-hires.md §4.6).
 *
 * Created by the {@see WaitlistResolver} when a
 * user adds a shortage to the waitlist. The availability-change listener flips an
 * Active monitor to Matched when freed-up stock would satisfy it; the scheduled
 * expiry job retires monitors past their `expires_at`. The monitor backs a
 * Monitoring {@see ShortageResolution} so the two stay in lockstep.
 *
 * @property int $id
 * @property int $shortage_resolution_id
 * @property int|null $opportunity_item_id
 * @property int $product_id
 * @property int $store_id
 * @property int $quantity_needed
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property WaitlistMonitorStatus $status
 * @property Carbon|null $matched_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShortageWaitlistMonitor extends Model
{
    /** @use HasFactory<ShortageWaitlistMonitorFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'shortage_resolution_id',
        'opportunity_item_id',
        'product_id',
        'store_id',
        'quantity_needed',
        'starts_at',
        'ends_at',
        'status',
        'matched_at',
        'notified_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_needed' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => WaitlistMonitorStatus::class,
            'matched_at' => 'datetime',
            'notified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ShortageResolution, $this>
     */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(ShortageResolution::class, 'shortage_resolution_id');
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
     * Scope to monitors still actively watching for availability.
     *
     * @param  Builder<ShortageWaitlistMonitor>  $query
     * @return Builder<ShortageWaitlistMonitor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WaitlistMonitorStatus::Active->value);
    }
}
