<?php

namespace Database\Seeders;

use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Database\Seeder;

class ListOfValuesSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'Address Type' => ['Billing', 'Shipping', 'Primary', 'Registered'],
            'Email Type' => ['Work', 'Personal', 'Billing', 'Support'],
            'Phone Type' => ['Work', 'Mobile', 'Home', 'Fax'],
            'Link Type' => ['Website', 'LinkedIn', 'Facebook', 'Instagram', 'X (Twitter)', 'YouTube'],
            'Relationship Type' => ['Employee', 'Director', 'Contractor', 'Agent'],
        ];

        foreach ($lists as $listName => $values) {
            $list = ListName::query()->updateOrCreate(
                ['name' => $listName],
                [
                    'description' => "{$listName} options",
                    'is_system' => true,
                ],
            );

            foreach ($values as $index => $value) {
                ListValue::query()->updateOrCreate(
                    ['list_name_id' => $list->id, 'name' => $value],
                    [
                        'sort_order' => $index,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
