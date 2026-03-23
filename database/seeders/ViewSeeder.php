<?php

namespace Database\Seeders;

use App\Models\CustomView;
use App\Views\ActivityColumnRegistry;
use App\Views\MemberColumnRegistry;
use App\Views\ProductColumnRegistry;
use App\Views\ProductGroupColumnRegistry;
use App\Views\StockLevelColumnRegistry;
use Illuminate\Database\Seeder;

class ViewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $memberDefaultColumns = (new MemberColumnRegistry)->defaultColumns();

        $memberViews = [
            $this->systemView('All Members', 'members', $memberDefaultColumns, isDefault: true),
            $this->systemView('Organisations Only', 'members', $memberDefaultColumns, filters: [
                ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'organisation'],
            ]),
            $this->systemView('Contacts Only', 'members', $memberDefaultColumns, filters: [
                ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'contact'],
            ]),
            $this->systemView('Active Venues', 'members', $memberDefaultColumns, filters: [
                ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'venue'],
                ['field' => 'is_active', 'predicate' => 'eq', 'value' => true],
            ]),
            $this->systemView('Inactive Members', 'members', $memberDefaultColumns, filters: [
                ['field' => 'is_active', 'predicate' => 'eq', 'value' => false],
            ]),
        ];

        $productDefaultColumns = (new ProductColumnRegistry)->defaultColumns();

        $productViews = [
            $this->systemView('All Products', 'products', $productDefaultColumns, isDefault: true),
            $this->systemView('Rental Products', 'products', $productDefaultColumns, filters: [
                ['field' => 'product_type', 'predicate' => 'eq', 'value' => 'rental'],
            ]),
            $this->systemView('Sale Products', 'products', $productDefaultColumns, filters: [
                ['field' => 'product_type', 'predicate' => 'eq', 'value' => 'sale'],
            ]),
            $this->systemView('Active Products', 'products', $productDefaultColumns, filters: [
                ['field' => 'is_active', 'predicate' => 'eq', 'value' => true],
            ]),
            $this->systemView('Inactive Products', 'products', $productDefaultColumns, filters: [
                ['field' => 'is_active', 'predicate' => 'eq', 'value' => false],
            ]),
        ];

        $stockLevelDefaultColumns = (new StockLevelColumnRegistry)->defaultColumns();

        $stockLevelViews = [
            $this->systemView('All Stock Levels', 'stock_levels', $stockLevelDefaultColumns, isDefault: true, sortColumn: 'item_name'),
            $this->systemView('Serialised Stock', 'stock_levels', $stockLevelDefaultColumns, filters: [
                ['field' => 'stock_category', 'predicate' => 'eq', 'value' => 50],
            ], sortColumn: 'item_name'),
            $this->systemView('Bulk Stock', 'stock_levels', $stockLevelDefaultColumns, filters: [
                ['field' => 'stock_category', 'predicate' => 'eq', 'value' => 10],
            ], sortColumn: 'item_name'),
        ];

        $activityDefaultColumns = (new ActivityColumnRegistry)->defaultColumns();

        $activityViews = [
            $this->systemView('All Activities', 'activities', $activityDefaultColumns, isDefault: true, sortColumn: 'created_at', sortDirection: 'desc'),
            $this->systemView('Scheduled Activities', 'activities', $activityDefaultColumns, filters: [
                ['field' => 'status_id', 'predicate' => 'eq', 'value' => 2001],
            ], sortColumn: 'starts_at'),
            $this->systemView('Completed Activities', 'activities', $activityDefaultColumns, filters: [
                ['field' => 'status_id', 'predicate' => 'eq', 'value' => 2002],
            ], sortColumn: 'created_at', sortDirection: 'desc'),
        ];

        $productGroupDefaultColumns = (new ProductGroupColumnRegistry)->defaultColumns();

        $productGroupViews = [
            $this->systemView('All Product Groups', 'product_groups', $productGroupDefaultColumns, isDefault: true),
        ];

        $allViews = array_merge($memberViews, $productViews, $stockLevelViews, $activityViews, $productGroupViews);

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

    /**
     * Build a system view definition array.
     *
     * @param  list<string>  $columns
     * @param  list<array{field: string, predicate: string, value: mixed}>  $filters
     * @return array<string, mixed>
     */
    private function systemView(
        string $name,
        string $entityType,
        array $columns,
        bool $isDefault = false,
        array $filters = [],
        string $sortColumn = 'name',
        string $sortDirection = 'asc',
    ): array {
        return [
            'name' => $name,
            'entity_type' => $entityType,
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => $isDefault,
            'columns' => $columns,
            'filters' => $filters,
            'sort_column' => $sortColumn,
            'sort_direction' => $sortDirection,
            'per_page' => 20,
            'config' => [],
        ];
    }
}
