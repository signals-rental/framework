<?php

namespace Database\Seeders;

use App\Models\RevenueGroup;
use Illuminate\Database\Seeder;

class RevenueGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ['Dry Hire', 'Wet Hire', 'Sales', 'Services'];

        foreach ($groups as $name) {
            RevenueGroup::query()->updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
