<?php

namespace App\Models;

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use Database\Factories\OpportunityItemAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read-optimised projection of an event-sourced per-asset assignment.
 *
 * One row links a specific physical asset (`stock_level_id`) to a line item and
 * tracks that asset's individual position through the dispatch/return cycle.
 * Carries NO business logic — every mutation flows through a Verbs asset event
 * (M5) whose handle() method dual-writes this row.
 *
 * The PK is application-assigned (allocated at event-fire time via
 * SequenceAllocator and baked into the AssetAllocated event), so Eloquent must
 * not auto-increment it.
 *
 * @property int $id
 * @property int $state_id
 * @property int $opportunity_item_id
 * @property int|null $stock_level_id
 * @property AssetAssignmentStatus $status
 * @property int|null $container_stock_level_id
 * @property Carbon|null $allocated_at
 * @property Carbon|null $prepared_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $checked_at
 * @property AssetCondition|null $condition_on_return
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OpportunityItemAsset extends Model
{
    /** @use HasFactory<OpportunityItemAssetFactory> */
    use HasFactory;

    /**
     * The PK is application-assigned (allocated at event-fire time and baked into
     * the AssetAllocated event), so Eloquent must not auto-increment it.
     */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'int';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'state_id',
        'opportunity_item_id',
        'stock_level_id',
        'status',
        'container_stock_level_id',
        'allocated_at',
        'prepared_at',
        'dispatched_at',
        'returned_at',
        'checked_at',
        'condition_on_return',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetAssignmentStatus::class,
            'condition_on_return' => AssetCondition::class,
            'allocated_at' => 'datetime',
            'prepared_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'returned_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class, 'opportunity_item_id');
    }

    /**
     * @return BelongsTo<StockLevel, $this>
     */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'stock_level_id');
    }

    /**
     * The kit/case container this asset is nested within, if any.
     *
     * @return BelongsTo<StockLevel, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'container_stock_level_id');
    }
}
