<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRule extends Model
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
