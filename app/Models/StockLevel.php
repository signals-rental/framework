<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockLevel extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\StockLevelFactory> */
    use HasCustomFields, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'product_id',
        'store_id',
        'member_id',
        'item_name',
        'asset_number',
        'serial_number',
        'barcode',
        'location',
        'stock_type',
        'stock_category',
        'quantity_held',
        'quantity_allocated',
        'quantity_unavailable',
        'quantity_on_order',
        'container_stock_level_id',
        'container_mode',
        'starts_at',
        'ends_at',
        'last_count_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_type' => 'integer',
            'stock_category' => 'integer',
            'quantity_held' => 'decimal:2',
            'quantity_allocated' => 'decimal:2',
            'quantity_unavailable' => 'decimal:2',
            'quantity_on_order' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_count_at' => 'datetime',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('product_id')->label('Product')
            ->relation('product', 'belongsTo', Product::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('store_id')->label('Store')
            ->relation('store', 'belongsTo', Store::class, 'name')
            ->required()->filterable()->sortable();
        $builder->relation('member_id')->label('Member')
            ->relation('member', 'belongsTo', Member::class, 'name')
            ->filterable();
        $builder->string('item_name')->label('Item Name')->searchable()->filterable()->sortable();
        $builder->string('asset_number')->label('Asset Number')->searchable()->filterable()->sortable();
        $builder->string('serial_number')->label('Serial Number')->searchable()->filterable()->sortable();
        $builder->string('barcode')->label('Barcode')->searchable()->filterable();
        $builder->string('location')->label('Location')->filterable()->sortable();
        $builder->integer('stock_type')->label('Stock Type')->filterable()->groupable();
        $builder->integer('stock_category')->label('Stock Category')->filterable()->groupable();
        $builder->decimal('quantity_held')->label('Quantity Held')->sortable();
        $builder->decimal('quantity_allocated')->label('Quantity Allocated')->sortable();
        $builder->decimal('quantity_unavailable')->label('Quantity Unavailable')->sortable();
        $builder->decimal('quantity_on_order')->label('Quantity on Order')->sortable();
        $builder->string('container_mode')->label('Container Mode')->filterable();
        $builder->datetime('starts_at')->label('Starts At')->filterable()->sortable();
        $builder->datetime('ends_at')->label('Ends At')->filterable()->sortable();
        $builder->datetime('last_count_at')->label('Last Count At')->sortable();
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
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The container stock level that holds this item.
     *
     * @return BelongsTo<self, $this>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(self::class, 'container_stock_level_id');
    }

    /**
     * Stock level items contained within this container.
     *
     * @return HasMany<self, $this>
     */
    public function containedItems(): HasMany
    {
        return $this->hasMany(self::class, 'container_stock_level_id');
    }

    /**
     * @return HasMany<StockTransaction, $this>
     */
    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'regarding');
    }

    /**
     * Scope to stock levels for a specific product.
     *
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to stock levels for a specific store.
     *
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope to stock levels with available quantity.
     *
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereRaw('quantity_held > (quantity_allocated + quantity_unavailable)');
    }

    /**
     * Scope to serialised stock levels.
     *
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeSerialized(Builder $query): Builder
    {
        return $query->where('stock_category', 50);
    }

    /**
     * Scope to bulk stock levels.
     *
     * @param  Builder<StockLevel>  $query
     * @return Builder<StockLevel>
     */
    public function scopeBulk(Builder $query): Builder
    {
        return $query->where('stock_category', 10);
    }
}
