<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\StockTransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'stock_level_id',
        'store_id',
        'source_id',
        'source_type',
        'transaction_type',
        'transaction_at',
        'quantity',
        'description',
        'manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'transaction_at' => 'datetime',
            'quantity' => 'decimal:2',
            'manual' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StockLevel, $this>
     */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Signed quantity: negative for reductions, positive for additions.
     */
    public function getQuantityMoveAttribute(): string
    {
        /** @var TransactionType $type */
        $type = $this->transaction_type;
        $signed = (float) $this->quantity * $type->quantitySign();

        return number_format($signed, 1, '.', '');
    }
}
