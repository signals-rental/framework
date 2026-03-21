<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            ListOfValuesSeeder::class,
            CurrencySeeder::class,
            TaxClassSeeder::class,
            TaxRateSeeder::class,
            RevenueGroupSeeder::class,
            CostGroupSeeder::class,
            ProductGroupSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            EmailTemplateSeeder::class,
            NotificationTypeSeeder::class,
            ViewSeeder::class,
            ProductSeeder::class,
            ActivitySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
