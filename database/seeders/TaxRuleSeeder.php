<?php

namespace Database\Seeders;

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use Illuminate\Database\Seeder;

/**
 * Seeds a default tax rule so that a clean install applies the standard
 * (non-zero) tax rate to the default organisation/product tax classes.
 *
 * Without this rule, TaxCalculator::calculate() falls back to zero tax for a
 * default product/organisation pairing. Depends on TaxClassSeeder and
 * TaxRateSeeder having run first.
 */
class TaxRuleSeeder extends Seeder
{
    public function run(): void
    {
        $orgClass = OrganisationTaxClass::query()->where('is_default', true)->first();
        $productClass = ProductTaxClass::query()->where('is_default', true)->first();
        $standardRate = TaxRate::query()->where('name', 'Standard')->first();

        if (! $orgClass || ! $productClass || ! $standardRate) {
            return;
        }

        TaxRule::query()->updateOrCreate(
            [
                'organisation_tax_class_id' => $orgClass->id,
                'product_tax_class_id' => $productClass->id,
            ],
            [
                'tax_rate_id' => $standardRate->id,
                'priority' => 0,
                'is_active' => true,
            ],
        );
    }
}
