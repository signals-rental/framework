<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Store::query()->exists()) {
            return;
        }

        Store::create([
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);
    }
}
