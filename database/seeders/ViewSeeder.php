<?php

namespace Database\Seeders;

use App\Models\CustomView;
use App\Views\ActivityColumnRegistry;
use App\Views\MemberColumnRegistry;
use App\Views\ProductColumnRegistry;
use App\Views\StockLevelColumnRegistry;
use Illuminate\Database\Seeder;

class ViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $memberRegistry = new MemberColumnRegistry;
        $memberDefaultColumns = $memberRegistry->defaultColumns();

        $memberViews = [
            [
                'name' => 'All Members',
                'entity_type' => 'members',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => true,
                'columns' => $memberDefaultColumns,
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
                'columns' => $memberDefaultColumns,
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
                'columns' => $memberDefaultColumns,
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
                'columns' => $memberDefaultColumns,
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
                'columns' => $memberDefaultColumns,
                'filters' => [
                    ['field' => 'is_active', 'predicate' => 'eq', 'value' => false],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
        ];

        $productRegistry = new ProductColumnRegistry;
        $productDefaultColumns = $productRegistry->defaultColumns();

        $productViews = [
            [
                'name' => 'All Products',
                'entity_type' => 'products',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => true,
                'columns' => $productDefaultColumns,
                'filters' => [],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Rental Products',
                'entity_type' => 'products',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $productDefaultColumns,
                'filters' => [
                    ['field' => 'product_type', 'predicate' => 'eq', 'value' => 'rental'],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Sale Products',
                'entity_type' => 'products',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $productDefaultColumns,
                'filters' => [
                    ['field' => 'product_type', 'predicate' => 'eq', 'value' => 'sale'],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Active Products',
                'entity_type' => 'products',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $productDefaultColumns,
                'filters' => [
                    ['field' => 'is_active', 'predicate' => 'eq', 'value' => true],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Inactive Products',
                'entity_type' => 'products',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $productDefaultColumns,
                'filters' => [
                    ['field' => 'is_active', 'predicate' => 'eq', 'value' => false],
                ],
                'sort_column' => 'name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
        ];

        $stockLevelRegistry = new StockLevelColumnRegistry;
        $stockLevelDefaultColumns = $stockLevelRegistry->defaultColumns();

        $stockLevelViews = [
            [
                'name' => 'All Stock Levels',
                'entity_type' => 'stock_levels',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => true,
                'columns' => $stockLevelDefaultColumns,
                'filters' => [],
                'sort_column' => 'item_name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Serialised Stock',
                'entity_type' => 'stock_levels',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $stockLevelDefaultColumns,
                'filters' => [
                    ['field' => 'stock_category', 'predicate' => 'eq', 'value' => 50],
                ],
                'sort_column' => 'item_name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Bulk Stock',
                'entity_type' => 'stock_levels',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $stockLevelDefaultColumns,
                'filters' => [
                    ['field' => 'stock_category', 'predicate' => 'eq', 'value' => 10],
                ],
                'sort_column' => 'item_name',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
        ];

        $activityRegistry = new ActivityColumnRegistry;
        $activityDefaultColumns = $activityRegistry->defaultColumns();

        $activityViews = [
            [
                'name' => 'All Activities',
                'entity_type' => 'activities',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => true,
                'columns' => $activityDefaultColumns,
                'filters' => [],
                'sort_column' => 'created_at',
                'sort_direction' => 'desc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Scheduled Activities',
                'entity_type' => 'activities',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $activityDefaultColumns,
                'filters' => [
                    ['field' => 'status_id', 'predicate' => 'eq', 'value' => 2001],
                ],
                'sort_column' => 'starts_at',
                'sort_direction' => 'asc',
                'per_page' => 20,
                'config' => [],
            ],
            [
                'name' => 'Completed Activities',
                'entity_type' => 'activities',
                'visibility' => 'system',
                'user_id' => null,
                'is_default' => false,
                'columns' => $activityDefaultColumns,
                'filters' => [
                    ['field' => 'status_id', 'predicate' => 'eq', 'value' => 2002],
                ],
                'sort_column' => 'created_at',
                'sort_direction' => 'desc',
                'per_page' => 20,
                'config' => [],
            ],
        ];

        $allViews = array_merge($memberViews, $productViews, $stockLevelViews, $activityViews);

        foreach ($allViews as $view) {
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
