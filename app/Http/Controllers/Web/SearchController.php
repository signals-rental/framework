<?php

namespace App\Http\Controllers\Web;

use App\Enums\MembershipType;
use App\Enums\ProductType;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->value();

        if (mb_strlen($query) < 2) {
            return response()->json([
                'members' => [],
                'products' => [],
                'stock_levels' => [],
                'product_groups' => [],
                'activities' => [],
            ]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        $results = [
            'members' => [],
            'products' => [],
            'stock_levels' => [],
            'product_groups' => [],
            'activities' => [],
        ];

        // Search members
        if (Gate::allows('members.view')) {
            $members = Member::query()
                ->where('name', 'ilike', '%'.$escaped.'%')
                ->orderBy('name')
                ->limit(8)
                ->get();

            foreach ($members as $member) {
                /** @var MembershipType $type */
                $type = $member->membership_type;
                $words = preg_split('/\s+/', trim($member->name));
                $initials = mb_strtoupper(
                    mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1)
                );
                $results['members'][] = [
                    'id' => $member->id,
                    'name' => $member->name,
                    'type' => $type->label(),
                    'typeValue' => $type->value,
                    'isActive' => $member->is_active,
                    'initials' => $initials,
                    'url' => route('members.show', $member->id),
                ];
            }
        }

        // Search products
        if (Gate::allows('products.view')) {
            $products = Product::query()
                ->where('name', 'ilike', '%'.$escaped.'%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            foreach ($products as $product) {
                /** @var ProductType $productType */
                $productType = $product->product_type;
                $results['products'][] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $productType->label(),
                    'typeValue' => $productType->value,
                    'url' => route('products.show', $product->id),
                ];
            }
        }

        // Search stock levels
        if (Gate::allows('stock.view')) {
            $stockLevels = StockLevel::query()
                ->where('asset_number', 'ilike', '%'.$escaped.'%')
                ->orderBy('asset_number')
                ->limit(5)
                ->get();

            foreach ($stockLevels as $stockLevel) {
                $results['stock_levels'][] = [
                    'id' => $stockLevel->id,
                    'name' => $stockLevel->item_name ?? ('Asset #'.$stockLevel->asset_number),
                    'type' => 'Stock Level',
                    'typeValue' => 'stock_level',
                    'url' => route('stock-levels.show', $stockLevel->id),
                ];
            }
        }

        // Search product groups
        if (Gate::allows('products.view')) {
            $productGroups = ProductGroup::query()
                ->where('name', 'ilike', '%'.$escaped.'%')
                ->orderBy('name')
                ->limit(5)
                ->get();

            foreach ($productGroups as $group) {
                $results['product_groups'][] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'type' => 'Product Group',
                    'typeValue' => 'product_group',
                    'url' => route('products.index', ['group' => $group->id]),
                ];
            }
        }

        // Search activities
        if (Gate::allows('activities.view')) {
            $activities = Activity::query()
                ->where('subject', 'ilike', '%'.$escaped.'%')
                ->with('owner')
                ->orderBy('subject')
                ->limit(5)
                ->get();

            foreach ($activities as $activity) {
                /** @var \App\Enums\ActivityType $activityType */
                $activityType = $activity->type_id;
                $results['activities'][] = [
                    'id' => $activity->id,
                    'name' => $activity->subject,
                    'type' => $activityType->label(),
                    'typeValue' => 'activity',
                    'url' => route('activities.show', $activity->id),
                ];
            }
        }

        return response()->json($results);
    }
}
