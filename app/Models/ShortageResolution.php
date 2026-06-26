<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
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
class ShortageResolution extends Model implements HasSchema
{
    use FormatsMoney;

    /** @use HasFactory<ShortageResolutionFactory> */
    use HasFactory;

    /**
     * Intentional deviation from the data-model soft-delete policy (which restricts
     * soft-deletes to opportunities/invoices/members). Shortage resolutions are the
     * durable audit trail of how a shortfall was covered — cancelling a resolution
     * must preserve the historical record (cost incurred, what was attempted) rather
     * than hard-deleting it. Decision: KEEP (recorded in
     * framework-plans/data-model-implementation.md soft-delete policy).
     */
    use SoftDeletes;

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
     * The application user (auth()->id()) who produced this resolution.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * The application user (auth()->id()) who confirmed this resolution.
     *
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
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

    /**
     * Scope to resolutions covering any line item of a given opportunity, via the
     * pivot's `opportunity_item_id` → `opportunity_items.opportunity_id` chain.
     *
     * @param  Builder<ShortageResolution>  $query
     * @return Builder<ShortageResolution>
     */
    public function scopeForOpportunity(Builder $query, int $opportunityId): Builder
    {
        return $query->whereHas(
            'items.opportunityItem',
            static fn (Builder $items): Builder => $items->where('opportunity_id', $opportunityId),
        );
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('resolver_key')->label('Resolver')->filterable()->sortable()->groupable();
        $builder->string('resolution_type')->label('Type')->filterable()->sortable()->groupable();
        $builder->string('status')->label('Status')->filterable()->sortable()->groupable();
        $builder->integer('quantity_resolved')->label('Quantity Resolved')->sortable();
        $builder->integer('cost')->label('Cost')->sortable();
        $builder->json('metadata')->label('Metadata');
        $builder->relation('resolved_by')->label('Resolved By')
            ->relation('resolver', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->relation('confirmed_by')->label('Confirmed By')
            ->relation('confirmer', 'belongsTo', User::class, 'name')
            ->filterable();
        $builder->datetime('confirmed_at')->label('Confirmed')->sortable()->filterable();
        $builder->datetime('fulfilled_at')->label('Fulfilled')->sortable()->filterable();
        $builder->datetime('cancelled_at')->label('Cancelled')->sortable()->filterable();
        $builder->string('cancellation_reason')->label('Cancellation Reason')->searchable();
        $builder->text('notes')->label('Notes')->searchable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
    }
}
