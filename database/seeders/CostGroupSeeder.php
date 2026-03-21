<?php

namespace Database\Seeders;

use App\Models\CostGroup;
use Illuminate\Database\Seeder;

class CostGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Equipment Sub-Hire',
            'Crew Sub-Hire',
            'Transport',
            'Consumables',
            'Equipment Purchase',
        ];

        foreach ($groups as $name) {
            CostGroup::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
