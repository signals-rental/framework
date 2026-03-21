<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Audio',
            'Lighting - Generic',
            'Lighting - Moving Heads',
            'Video',
            'Staging',
            'Power',
            'Rigging',
            'Furniture',
            'Transport',
            'Consumables',
        ];

        foreach ($groups as $index => $name) {
            ProductGroup::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index],
            );
        }
    }
}
