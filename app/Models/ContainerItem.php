<?php

namespace App\Models;

use App\Enums\ContainerItemUnpackReason;
use Database\Factories\ContainerItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One membership row: a serialised item packed into a container. The row is
 * active while {@see $unpacked_at} is null; closing it records when and why the
 * item left (and, for a transfer, which container it moved to).
 *
 * A container demand (`source_type = 'container'`) is keyed to this row's id for
 * kit/hybrid-fixed memberships — packing creates the demand, unpacking voids it
 * (serialised-containers.md §"Kit Mode — Contents Removed from Availability").
 *
 * @property int $id
 * @property int $container_id
 * @property int $serialised_item_id
 * @property int $product_id
 * @property Carbon $packed_at
 * @property int|null $packed_by_user_id
 * @property Carbon|null $unpacked_at
 * @property ContainerItemUnpackReason|null $unpacked_reason
 * @property int|null $transferred_to_container_id
 * @property int|null $auto_returned_from_opportunity_id
 * @property int|null $returned_from_opportunity_id
 * @property string|null $position
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContainerItem extends Model
{
    /** @use HasFactory<ContainerItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'container_id',
        'serialised_item_id',
        'product_id',
        'packed_at',
        'packed_by_user_id',
        'unpacked_at',
        'unpacked_reason',
        'transferred_to_container_id',
        'auto_returned_from_opportunity_id',
        'returned_from_opportunity_id',
        'position',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'packed_at' => 'datetime',
            'unpacked_at' => 'datetime',
            'unpacked_reason' => ContainerItemUnpackReason::class,
            'auto_returned_from_opportunity_id' => 'integer',
            'returned_from_opportunity_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Container, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /**
     * The packed serialised item (a serialised stock level).
     *
     * @return BelongsTo<StockLevel, $this>
     */
    public function serialisedItem(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class, 'serialised_item_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether this membership is still active (the item is currently packed).
     */
    public function isActive(): bool
    {
        return $this->unpacked_at === null;
    }

    /**
     * Scope to active (currently-packed) memberships.
     *
     * @param  Builder<ContainerItem>  $query
     * @return Builder<ContainerItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unpacked_at');
    }
}
