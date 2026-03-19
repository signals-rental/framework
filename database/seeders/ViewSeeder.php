<?php

namespace Database\Seeders;

use App\Models\CustomView;
use App\Views\MemberColumnRegistry;
use Illuminate\Database\Seeder;

class ViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $registry = new MemberColumnRegistry;
        $defaultColumns = $registry->defaultColumns();

        $views = [
            [
                'name' => 'All Members',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => true,
                'columns' => $defaultColumns,
                'filters' => [],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Organisations Only',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $defaultColumns,
                'filters' => [
                    ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation'],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Contacts Only',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $defaultColumns,
                'filters' => [
                    ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'contact'],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Active Venues',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $defaultColumns,
                'filters' => [
                    ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'venue'],
                    ['field' => 'is_active', 'predicate' => 'eq', 'value' => true],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Inactive Members',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $defaultColumns,
                'filters' => [
                    ['field' => 'is_active', 'predicate' => 'eq', 'value' => false],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
        ];

        foreach ($views as $view) {
            CustomView::query()->updateOrCreate(
                [
                    'name' => $view['name'],
                    'entity_type' => $view['entity_type'],
                ],
                $view,
            );
        }
    }
}
