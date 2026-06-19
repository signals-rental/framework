<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Enums\AllowedStockType;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Traits\FormatsMoney;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCustomFields;
use App\Services\SchemaBuilder;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property StockMethod|null $stock_method
 * @property int $buffer_before_minutes
 * @property int $post_rent_unavailability
 * @property bool $track_availability
 * @property bool $is_kit
 */
class Product extends Model implements HasSchema
{
    /** @use HasFactory<ProductFactory> */
    use FormatsMoney, HasAttachments, HasCustomFields, HasFactory, SoftDeletes;

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
        'buffer_before_minutes',
        'post_rent_unavailability',
        'track_availability',
        'is_kit',
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
            'allowed_stock_type' => AllowedStockType::class,
            'stock_method' => StockMethod::class,
            'is_active' => 'boolean',
            'accessory_only' => 'boolean',
            'system' => 'boolean',
            'discountable' => 'boolean',
            'tag_list' => 'array',
            'weight' => 'decimal:4',
            'buffer_percent' => 'decimal:2',
            'buffer_before_minutes' => 'integer',
            'post_rent_unavailability' => 'integer',
            'track_availability' => 'boolean',
            'is_kit' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->string('name')->label('Name')->required()->searchable()->filterable()->sortable();
        $builder->text('description')->label('Description')->searchable();
        $builder->enum('product_type')->label('Product Type')->filterable()->sortable()->groupable();
        $builder->integer('allowed_stock_type')->label('Allowed Stock Type')->filterable()->groupable();
        $builder->enum('stock_method')->label('Stock Method')->filterable()->sortable()->groupable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->boolean('accessory_only')->label('Accessory Only')->filterable();
        $builder->boolean('system')->label('System')->filterable()->groupable();
        $builder->boolean('discountable')->label('Discountable')->filterable();
        $builder->string('barcode')->label('Barcode')->searchable()->filterable();
        $builder->string('sku')->label('SKU')->searchable()->filterable();
        $builder->decimal('weight')->label('Weight')->sortable();
        $builder->integer('replacement_charge')->label('Replacement Charge')->sortable();
        $builder->decimal('buffer_percent')->label('Buffer %')->sortable();
        $builder->integer('buffer_before_minutes')->label('Buffer Before (minutes)');
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
        $builder->relation('sub_rental_cost_group_id')->label('Sub-Rental Cost Group')
            ->relation('subRentalCostGroup', 'belongsTo', CostGroup::class, 'name')
            ->filterable();
        $builder->relation('purchase_cost_group_id')->label('Purchase Cost Group')
            ->relation('purchaseCostGroup', 'belongsTo', CostGroup::class, 'name')
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
     * Rate assignments for this product.
     *
     * @return HasMany<ProductRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
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
     * Kit composition rows where this product is the kit parent — i.e. the
     * components drawn into this kit (availability-engine.md §"Non-Serialised
     * Kits"). A product is a kit when this relation is non-empty.
     *
     * @return HasMany<SerialisedComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(SerialisedComponent::class, 'product_id');
    }

    /**
     * Kit composition rows where this product is used as a component of other
     * kits.
     *
     * @return HasMany<SerialisedComponent, $this>
     */
    public function componentOf(): HasMany
    {
        return $this->hasMany(SerialisedComponent::class, 'component_product_id');
    }

    /**
     * Whether this product is a kit — composed of other products read-time rather
     * than demand-tracked directly. Prefers the denormalised `is_kit` flag and
     * falls back to composition existence so a freshly-built kit (flag not yet
     * persisted) still reports correctly.
     */
    public function isKit(): bool
    {
        if ($this->is_kit) {
            return true;
        }

        return $this->components()->exists();
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
     * Scope to products belonging to the given product group.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('product_group_id', $groupId);
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
     * Derive a human-readable name for the allowed stock type.
     */
    public static function stockTypeName(int $type): string
    {
        return AllowedStockType::tryFrom($type)?->label() ?? 'Unknown';
    }
}
