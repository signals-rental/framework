<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\RateTransactionType;
use App\Models\Traits\FormatsMoney;
use App\Services\SchemaBuilder;
use Carbon\CarbonInterface;
use Database\Factories\ProductRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $valid_from
 * @property CarbonInterface|null $valid_to
 */
class ProductRate extends Model implements HasSchema
{
    /** @use HasFactory<ProductRateFactory> */
    use FormatsMoney, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'rate_definition_id',
        'store_id',
        'transaction_type',
        'price',
        'currency',
        'valid_from',
        'valid_to',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => RateTransactionType::class,
            'price' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'priority' => 'integer',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('product_id')->label('Product')
            ->relation('product', 'belongsTo', Product::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('rate_definition_id')->label('Rate Definition')
            ->relation('rateDefinition', 'belongsTo', RateDefinition::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->filterable()->sortable();
        $builder->enum('transaction_type')->label('Transaction Type')->required()->filterable()->sortable()->groupable();
        $builder->integer('price')->label('Price')->required()->sortable();
        $builder->string('currency')->label('Currency')->required()->filterable();
        $builder->date('valid_from')->label('Valid From')->filterable()->sortable();
        $builder->date('valid_to')->label('Valid To')->filterable()->sortable();
        $builder->integer('priority')->label('Priority')->filterable()->sortable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<RateDefinition, $this>
     */
    public function rateDefinition(): BelongsTo
    {
        return $this->belongsTo(RateDefinition::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
