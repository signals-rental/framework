<?php

namespace App\Data\Products;

use App\Enums\TransactionType;
use App\Models\StockTransaction;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class StockTransactionData extends Data
{
    public function __construct(
        public int $id,
        public int $stock_level_id,
        public int $store_id,
        public ?int $source_id,
        public ?string $source_type,
        public int $transaction_type,
        public string $transaction_type_name,
        public string $transaction_at,
        public string $quantity,
        public string $quantity_move,
        public ?string $description,
        public bool $manual,
        public string $created_at,
        public string $updated_at,
    ) {}

    private static function formatTimestamp(\DateTimeInterface $timestamp): string
    {
        return Carbon::instance($timestamp)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function fromModel(StockTransaction $transaction): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $transaction->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $transaction->updated_at;

        /** @var Carbon $transactionAt */
        $transactionAt = $transaction->transaction_at;

        /** @var TransactionType $type */
        $type = $transaction->transaction_type;

        return new self(
            id: $transaction->id,
            stock_level_id: $transaction->stock_level_id,
            store_id: $transaction->store_id,
            source_id: $transaction->source_id,
            source_type: $transaction->source_type,
            transaction_type: $type->value,
            transaction_type_name: $type->label(),
            transaction_at: self::formatTimestamp($transactionAt),
            quantity: number_format((float) $transaction->quantity, 1, '.', ''),
            quantity_move: $transaction->quantity_move,
            description: $transaction->description,
            manual: $transaction->manual,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
        );
    }
}
