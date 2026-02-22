<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->createDemoStores();
    }

    private function createDemoStores(): void
    {
        $demoStores = [
            [
                'name' => 'London Warehouse',
                'street' => '123 Industrial Way',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'E1 6AN',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
            ],
            [
                'name' => 'Manchester Depot',
                'street' => '45 Trade Park',
                'city' => 'Manchester',
                'county' => 'Greater Manchester',
                'postcode' => 'M1 1AA',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
            ],
            [
                'name' => 'Edinburgh Office',
                'street' => '78 Festival Square',
                'city' => 'Edinburgh',
                'county' => 'Midlothian',
                'postcode' => 'EH1 1BB',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
            ],
        ];

        foreach ($demoStores as $storeData) {
            Store::create($storeData);
        }
    }
}
