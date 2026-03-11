<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/countries.json');

        if (! file_exists($path)) {
            $this->command->warn('countries.json not found, skipping CountrySeeder.');

            return;
        }

        $countries = json_decode(file_get_contents($path), true);

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['code' => $country['code']],
                $country,
            );
        }
    }
}
