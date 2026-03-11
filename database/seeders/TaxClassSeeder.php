<?php

namespace Database\Seeders;

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use Illuminate\Database\Seeder;

class TaxClassSeeder extends Seeder
{
    public function run(): void
    {
        OrganisationTaxClass::query()->updateOrCreate(
            ['name' => 'Standard'],
            ['description' => 'Standard tax treatment', 'is_default' => true],
        );

        ProductTaxClass::query()->updateOrCreate(
            ['name' => 'Standard'],
            ['description' => 'Standard tax rate', 'is_default' => true],
        );

        ProductTaxClass::query()->updateOrCreate(
            ['name' => 'Exempt'],
            ['description' => 'Tax exempt', 'is_default' => false],
        );
    }
}
