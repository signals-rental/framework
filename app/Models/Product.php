<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasAttachments, HasCustomFields, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'product_group_id',
        'product_type',
        'allowed_stock_type',
        'stock_method',
        'weight',
        'barcode',
        'sku',
        'replacement_charge',
        'buffer_percent',
        'post_rent_unavailability',
        'is_active',
        'accessory_only',
        'system',
        'discountable',
        'tax_class_id',
        'purchase_tax_class_id',
        'rental_revenue_group_id',
        'sale_revenue_group_id',
        'sub_rental_cost_group_id',
        'sub_rental_price',
        'purchase_cost_group_id',
        'purchase_price',
        'country_of_origin_id',
        'tag_list',
        'icon_url',
        'icon_thumb_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'stock_method' => StockMethod::class,
            'is_active' => 'boolean',
            'accessory_only' => 'boolean',
            'system' => 'boolean',
            'discountable' => 'boolean',
            'tag_list' => 'array',
            'weight' => 'decimal:4',
            'buffer_percent' => 'decimal:2',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->enum('product_type')->label('Product Type')->filterable()->sortable()->groupable();
        $builder->enum('stock_method')->label('Stock Method')->filterable()->sortable()->groupable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->boolean('accessory_only')->label('Accessory Only')->filterable();
        $builder->boolean('discountable')->label('Discountable')->filterable();
        $builder->string('barcode')->label('Barcode')->searchable()->filterable();
        $builder->string('sku')->label('SKU')->searchable()->filterable();
        $builder->decimal('weight')->label('Weight')->sortable();
        $builder->integer('replacement_charge')->label('Replacement Charge')->sortable();
        $builder->decimal('buffer_percent')->label('Buffer %')->sortable();
        $builder->integer('post_rent_unavailability')->label('Post-Rent Unavailability');
        $builder->relation('product_group_id')->label('Product Group')
            ->relation('productGroup', 'belongsTo', ProductGroup::class, 'name')
            ->filterable();
        $builder->relation('tax_class_id')->label('Tax Class')
            ->relation('taxClass', 'belongsTo', ProductTaxClass::class, 'name')
            ->filterable();
        $builder->relation('purchase_tax_class_id')->label('Purchase Tax Class')
            ->relation('purchaseTaxClass', 'belongsTo', ProductTaxClass::class, 'name')
            ->filterable();
        $builder->relation('rental_revenue_group_id')->label('Rental Revenue Group')
            ->relation('rentalRevenueGroup', 'belongsTo', RevenueGroup::class, 'name')
            ->filterable();
        $builder->relation('sale_revenue_group_id')->label('Sale Revenue Group')
            ->relation('saleRevenueGroup', 'belongsTo', RevenueGroup::class, 'name')
            ->filterable();
        $builder->relation('country_of_origin_id')->label('Country of Origin')
            ->relation('countryOfOrigin', 'belongsTo', Country::class, 'name')
            ->filterable();
        $builder->json('tag_list')->label('Tags')->searchable();
        $builder->integer('sub_rental_price')->label('Sub-Rental Price')->sortable();
        $builder->integer('purchase_price')->label('Purchase Price')->sortable();
        $builder->datetime('created_at')->label('Created')->sortable()->filterable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class, 'tax_class_id');
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function purchaseTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class, 'purchase_tax_class_id');
    }

    /**
     * @return BelongsTo<RevenueGroup, $this>
     */
    public function rentalRevenueGroup(): BelongsTo
    {
        return $this->belongsTo(RevenueGroup::class, 'rental_revenue_group_id');
    }

    /**
     * @return BelongsTo<RevenueGroup, $this>
     */
    public function saleRevenueGroup(): BelongsTo
    {
        return $this->belongsTo(RevenueGroup::class, 'sale_revenue_group_id');
    }

    /**
     * @return BelongsTo<CostGroup, $this>
     */
    public function subRentalCostGroup(): BelongsTo
    {
        return $this->belongsTo(CostGroup::class, 'sub_rental_cost_group_id');
    }

    /**
     * @return BelongsTo<CostGroup, $this>
     */
    public function purchaseCostGroup(): BelongsTo
    {
        return $this->belongsTo(CostGroup::class, 'purchase_cost_group_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function countryOfOrigin(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_of_origin_id');
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Accessories attached to this product.
     *
     * @return HasMany<Accessory, $this>
     */
    public function accessories(): HasMany
    {
        return $this->hasMany(Accessory::class, 'product_id');
    }

    /**
     * Products that use this product as an accessory.
     *
     * @return HasMany<Accessory, $this>
     */
    public function accessoryOf(): HasMany
    {
        return $this->hasMany(Accessory::class, 'accessory_product_id');
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'regarding');
    }

    /**
     * Scope to active products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to products of a given type.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOfType(Builder $query, ProductType $type): Builder
    {
        return $query->where('product_type', $type);
    }

    /**
     * Scope to rental products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeRental(Builder $query): Builder
    {
        return $query->where('product_type', ProductType::Rental);
    }

    /**
     * Scope to sale products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSale(Builder $query): Builder
    {
        return $query->where('product_type', ProductType::Sale);
    }

    /**
     * Scope to service products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeService(Builder $query): Builder
    {
        return $query->where('product_type', ProductType::Service);
    }

    /**
     * Scope to archived (soft-deleted) products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed();
    }

    /**
     * Scope to include archived (soft-deleted) products.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWithArchived(Builder $query): Builder
    {
        return $query->withTrashed();
    }

    /**
     * Format a money value from minor units to decimal string for API responses.
     */
    public function formatMoneyCost(string $attribute): string
    {
        $value = (int) $this->getAttribute($attribute);

        return number_format($value / 100, 2, '.', '');
    }
}
