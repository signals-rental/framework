<?php

namespace App\Models;

use App\Enums\KitComponentBinding;
use Database\Factories\SerialisedComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a kit composition: the kit parent product, the component product,
 * and the quantity of that component per single kit.
 *
 * A product is a kit when it owns one or more of these rows
 * (availability-engine.md §"Non-Serialised Kits"). Kit availability is composed
 * read-time from component availability — the kit itself holds no snapshots and
 * generates no demand of its own.
 *
 * @property int $id
 * @property int $product_id
 * @property int $component_product_id
 * @property numeric-string $quantity
 * @property KitComponentBinding $binding
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SerialisedComponent extends Model
{
    /** @use HasFactory<SerialisedComponentFactory> */
    use HasFactory;

    protected $table = 'serialised_components';

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'component_product_id',
        'quantity',
        'binding',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'binding' => KitComponentBinding::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * The kit parent product this component belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * The component product drawn into the kit.
     *
     * @return BelongsTo<Product, $this>
     */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
