<?php

namespace App\Models;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRule extends Model implements HasSchema
{
    /** @use HasFactory<\Database\Factories\TaxRuleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'organisation_tax_class_id',
        'product_tax_class_id',
        'tax_rate_id',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->relation('organisation_tax_class_id')->label('Organisation Tax Class')
            ->relation('organisationTaxClass', 'belongsTo', OrganisationTaxClass::class, 'name')
            ->filterable();
        $builder->relation('product_tax_class_id')->label('Product Tax Class')
            ->relation('productTaxClass', 'belongsTo', ProductTaxClass::class, 'name')
            ->filterable();
        $builder->relation('tax_rate_id')->label('Tax Rate')
            ->relation('taxRate', 'belongsTo', TaxRate::class, 'name')
            ->filterable();
        $builder->integer('priority')->label('Priority')->sortable();
        $builder->boolean('is_active')->label('Active')->filterable()->sortable()->groupable();
        $builder->datetime('created_at')->label('Created')->sortable();
        $builder->datetime('updated_at')->label('Updated')->sortable();
    }

    /**
     * @return BelongsTo<OrganisationTaxClass, $this>
     */
    public function organisationTaxClass(): BelongsTo
    {
        return $this->belongsTo(OrganisationTaxClass::class);
    }

    /**
     * @return BelongsTo<ProductTaxClass, $this>
     */
    public function productTaxClass(): BelongsTo
    {
        return $this->belongsTo(ProductTaxClass::class);
    }

    /**
     * @return BelongsTo<TaxRate, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
