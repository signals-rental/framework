<?php

namespace App\Models;

use App\Enums\DemandPhase;
use App\Observers\DemandObserver;
use Carbon\CarbonInterface;
use Database\Factories\DemandFactory;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A single claim against stock for a product at a store over a time window.
 *
 * Demands are the authoritative source for the availability engine — snapshots
 * are derived from them. Active demands (phases Committed/Operational, cached in
 * `is_active`) reduce availability; inactive ones (Draft/Closed/Void) do not.
 *
 * Serialised products produce one demand per allocated asset (`asset_id` set,
 * `quantity = 1`); bulk products produce a single demand (`asset_id` null,
 * `quantity >= 1`). On PostgreSQL the `period` column is a `tstzrange` carrying
 * the full unavailable window (with product buffers baked in); `starts_at` /
 * `ends_at` retain the original pre-buffer dates. The `period` column is absent
 * on the SQLite test connection — see the create migration.
 *
 * The same buffered window is ALSO snapshotted into the `buffered_starts_at` /
 * `buffered_ends_at` columns on every driver, so the per-slot PHP attribution
 * loops and the SQLite scalar overlap path are buffer-aware (and agree with the
 * Postgres `period &&` fetch). They are written from {@see bufferedPeriod()} at
 * demand-write time and read back via {@see bufferedStartsAt()} /
 * {@see bufferedEndsAt()}, which fall back to the raw dates when null
 * (zero-buffer / legacy rows). Snapshotted (never recomputed from live config)
 * so a Verbs replay never diverges when product buffers change.
 *
 * @property int $id
 * @property int $product_id
 * @property int $store_id
 * @property int|null $asset_id
 * @property int $quantity
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $buffered_starts_at
 * @property Carbon|null $buffered_ends_at
 * @property string $source_type
 * @property int $source_id
 * @property DemandPhase $phase
 * @property bool $is_active
 * @property int $priority
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(DemandObserver::class)]
class Demand extends Model
{
    /** @use HasFactory<DemandFactory> */
    use HasFactory;

    /**
     * Sentinel end date for demands with no known end (overdue items,
     * indefinite quarantine, open-ended put-asides). Used instead of NULL so
     * every range query uses the same pattern, and so the value falls far
     * outside the snapshot generation window.
     */
    public const string SENTINEL_DATE = '2199-01-01T00:00:00Z';

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'store_id',
        'asset_id',
        'quantity',
        'starts_at',
        'ends_at',
        'buffered_starts_at',
        'buffered_ends_at',
        'source_type',
        'source_id',
        'phase',
        'is_active',
        'priority',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'buffered_starts_at' => 'datetime',
            'buffered_ends_at' => 'datetime',
            'phase' => DemandPhase::class,
            'is_active' => 'boolean',
            'priority' => 'integer',
            'metadata' => 'array',
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
     * The specific serialised stock level this demand claims (null for bulk).
     *
     * @return BelongsTo<StockLevel, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'asset_id');
    }

    /**
     * The originating entity (opportunity item, quarantine record, transfer, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    /**
     * Scope to demands that currently consume availability.
     *
     * @param  Builder<Demand>  $query
     * @return Builder<Demand>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to demands whose window overlaps the given range.
     *
     * On PostgreSQL this uses the native `tstzrange` overlap operator (`&&`)
     * against the buffered `period` column. On other drivers (SQLite test
     * suite, which has no `period` column) it falls back to a scalar overlap on
     * the BUFFERED bounds: a half-open `[start, end)` comparison so demands are
     * considered overlapping when one starts strictly before the other ends.
     *
     * The scalar branch uses `COALESCE(buffered_starts_at, starts_at)` /
     * `COALESCE(buffered_ends_at, ends_at)` so it matches the buffered window
     * Postgres queries via `period &&` — fetch and per-slot attribution then
     * agree on every driver, even across a demand's prep/turnaround window.
     * Zero-buffer and legacy rows (null buffered columns) fall back to the raw
     * dates, preserving the previous behaviour.
     *
     * @param  Builder<Demand>  $query
     * @return Builder<Demand>
     */
    public function scopeOverlapping(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $start = $start instanceof Carbon ? $start : Carbon::parse($start);
        $end = $end instanceof Carbon ? $end : Carbon::parse($end);

        if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
            return $query->whereRaw(
                'period && tstzrange(?, ?)',
                [$start->toIso8601String(), $end->toIso8601String()],
            );
        }

        return $query
            ->whereRaw('COALESCE(buffered_starts_at, starts_at) < ?', [$end])
            ->whereRaw('COALESCE(buffered_ends_at, ends_at) > ?', [$start]);
    }

    /**
     * The buffered (turnaround-inclusive) start of this demand's unavailable
     * window, falling back to the raw {@see $starts_at} when no buffered bound is
     * stored (zero-buffer or legacy rows).
     */
    public function bufferedStartsAt(): CarbonInterface
    {
        return $this->buffered_starts_at ?? $this->starts_at;
    }

    /**
     * The buffered (turnaround-inclusive) end of this demand's unavailable
     * window, falling back to the raw {@see $ends_at} when no buffered bound is
     * stored (zero-buffer or legacy rows).
     */
    public function bufferedEndsAt(): CarbonInterface
    {
        return $this->buffered_ends_at ?? $this->ends_at;
    }

    /**
     * Scope to demands with a known end date (not the sentinel).
     *
     * @param  Builder<Demand>  $query
     * @return Builder<Demand>
     */
    public function scopeDefinite(Builder $query): Builder
    {
        return $query->where('ends_at', '<', static::sentinel());
    }

    /**
     * Scope to indefinite demands (ending at the sentinel date).
     *
     * @param  Builder<Demand>  $query
     * @return Builder<Demand>
     */
    public function scopeIndefinite(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', static::sentinel());
    }

    /**
     * Whether this demand has no known end date (ends at the sentinel).
     */
    public function getIsIndefiniteAttribute(): bool
    {
        return $this->ends_at->greaterThanOrEqualTo(static::sentinel());
    }

    /**
     * The sentinel "no known end" date as a Carbon instance.
     */
    public static function sentinel(): Carbon
    {
        return Carbon::parse(self::SENTINEL_DATE);
    }

    /**
     * Compose the buffered availability window for a demand from its pre-buffer
     * dates and the product's before/after buffer minutes.
     *
     * Returns the half-open `[start, end)` boundaries the `period` range should
     * carry: the start pulled earlier by the prep (before) buffer and the end
     * pushed later by the turnaround (after) buffer. Buffers are clamped to a
     * floor of zero. The end is left at the sentinel untouched when indefinite —
     * extending the sentinel further is meaningless.
     *
     * The same boundaries are persisted into the `buffered_starts_at` /
     * `buffered_ends_at` columns (read back via {@see bufferedStartsAt()} /
     * {@see bufferedEndsAt()}) so the per-slot PHP attribution and the SQLite
     * scalar overlap path are buffer-aware on every driver.
     *
     * @return array{0: Carbon, 1: Carbon} [bufferedStart, bufferedEnd]
     */
    public static function bufferedPeriod(
        Carbon $startsAt,
        Carbon $endsAt,
        int $bufferBeforeMinutes = 0,
        int $bufferAfterMinutes = 0,
    ): array {
        $bufferedStart = $startsAt->copy()->subMinutes(max(0, $bufferBeforeMinutes));

        $bufferedEnd = $endsAt->greaterThanOrEqualTo(static::sentinel())
            ? static::sentinel()
            : $endsAt->copy()->addMinutes(max(0, $bufferAfterMinutes));

        return [$bufferedStart, $bufferedEnd];
    }

    /**
     * Build a PostgreSQL `tstzrange` literal expression for the given window,
     * for use when writing the `period` column. Half-open `[start, end)`.
     *
     * Returns a raw query expression. Only meaningful on PostgreSQL — callers
     * guard on the driver before persisting the `period` column.
     */
    public static function periodExpression(Carbon $start, Carbon $end): Expression
    {
        return DB::raw(sprintf(
            "tstzrange('%s', '%s', '[)')",
            $start->toIso8601String(),
            $end->toIso8601String(),
        ));
    }
}
