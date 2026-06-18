<?php

namespace App\Models;

use Database\Factories\ShortageResolutionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The resolution-to-opportunity-item bridge (shortage-resolution-sub-hires.md
 * §8.2): how many units of a resolution serve a specific line item.
 *
 * @property int $id
 * @property int $shortage_resolution_id
 * @property int $opportunity_item_id
 * @property int $quantity_allocated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShortageResolutionItem extends Model
{
    /** @use HasFactory<ShortageResolutionItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'shortage_resolution_id',
        'opportunity_item_id',
        'quantity_allocated',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_allocated' => 'integer',
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
     * @return BelongsTo<OpportunityItem, $this>
     */
    public function opportunityItem(): BelongsTo
    {
        return $this->belongsTo(OpportunityItem::class, 'opportunity_item_id');
    }
}
