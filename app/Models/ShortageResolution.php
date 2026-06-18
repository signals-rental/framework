<?php

namespace App\Models;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use Database\Factories\ShortageResolutionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A persisted resolution recorded against a (computed, transient) shortage
 * (shortage-resolution-sub-hires.md §8.1).
 *
 * Resolutions are the durable trail: each carries the resolver that produced it,
 * the type and status, how much it covers (`quantity_resolved`), any cost in
 * minor units, and resolver-specific `metadata`. The per-item allocation lives on
 * the {@see ShortageResolutionItem} pivot.
 *
 * @property int $id
 * @property string $resolver_key
 * @property ShortageResolutionType $resolution_type
 * @property ShortageResolutionStatus $status
 * @property int $quantity_resolved
 * @property int|null $cost
 * @property array<string, mixed>|null $metadata
 * @property int|null $resolved_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ShortageResolution extends Model
{
    /** @use HasFactory<ShortageResolutionFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'resolver_key',
        'resolution_type',
        'status',
        'quantity_resolved',
        'cost',
        'metadata',
        'resolved_by',
        'confirmed_by',
        'confirmed_at',
        'fulfilled_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolution_type' => ShortageResolutionType::class,
            'status' => ShortageResolutionStatus::class,
            'quantity_resolved' => 'integer',
            'cost' => 'integer',
            'metadata' => 'array',
            'confirmed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ShortageResolutionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShortageResolutionItem::class, 'shortage_resolution_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'confirmed_by');
    }

    /**
     * Scope to resolutions that still actively cover shortfall (i.e. not
     * cancelled or failed).
     *
     * @param  Builder<ShortageResolution>  $query
     * @return Builder<ShortageResolution>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ShortageResolutionStatus::Cancelled->value,
            ShortageResolutionStatus::Failed->value,
        ]);
    }

    /**
     * Scope to resolutions covering a given opportunity item (via the pivot).
     *
     * @param  Builder<ShortageResolution>  $query
     * @return Builder<ShortageResolution>
     */
    public function scopeForItem(Builder $query, int $opportunityItemId): Builder
    {
        return $query->whereHas(
            'items',
            static fn (Builder $items): Builder => $items->where('opportunity_item_id', $opportunityItemId),
        );
    }
}
