<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\Traits\HasCustomFields;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for the set of modules (entities) that support custom fields.
 *
 * The authoritative capability is the set of Eloquent models that use the
 * {@see HasCustomFields} trait. This registry pairs each of those models with a
 * human-readable label and exposes the resulting module-type list to every
 * consumer (admin field-definition form, API documentation, validation), so a
 * new custom-field-capable model only needs registering in one place instead of
 * the three previously-unsynchronised sites (trait usage, API `$customFieldModule`
 * declarations, and the hardcoded admin dropdown).
 *
 * The module-type string for each model matches the runtime resolution key
 * produced by {@see HasCustomFields::customFieldModuleType()} (i.e.
 * `class_basename($model)`), which is also the value stored in
 * `custom_fields.module_type` and declared by the API controllers'
 * `$customFieldModule` property.
 */
class CustomFieldModuleRegistry
{
    /**
     * The custom-field-capable models, mapped to their human-readable labels.
     *
     * Every model listed here MUST use the {@see HasCustomFields} trait. The
     * `CustomFieldModuleRegistryTest` meta-test guards this contract and asserts
     * the list stays in sync with the API controllers' `$customFieldModule`
     * declarations (no missing capability, no phantom entry without a backing model).
     *
     * @var array<class-string<Model>, string>
     */
    private const MODELS = [
        Member::class => 'Member',
        Product::class => 'Product',
        ProductGroup::class => 'Product Group',
        StockLevel::class => 'Stock Level',
        Activity::class => 'Activity',
        Store::class => 'Store',
        Opportunity::class => 'Opportunity',
    ];

    /**
     * Module-type string => human-readable label.
     *
     * The keys are the values stored in `custom_fields.module_type` and used by
     * {@see HasCustomFields::customFieldModuleType()}.
     *
     * @return array<string, string>
     */
    public function modules(): array
    {
        $modules = [];

        foreach (self::MODELS as $model => $label) {
            $modules[class_basename($model)] = $label;
        }

        return $modules;
    }

    /**
     * The module-type string keys only (e.g. ['Member', 'Product', ...]).
     *
     * @return list<string>
     */
    public function moduleTypes(): array
    {
        return array_keys($this->modules());
    }

    /**
     * The backing model class-strings for the registered modules.
     *
     * @return list<class-string<Model>>
     */
    public function models(): array
    {
        return array_keys(self::MODELS);
    }

    /**
     * Whether the given module-type string is a registered custom-field module.
     */
    public function has(string $moduleType): bool
    {
        return array_key_exists($moduleType, $this->modules());
    }

    /**
     * The human-readable label for a module-type string, or the string itself
     * if it is not registered.
     */
    public function label(string $moduleType): string
    {
        return $this->modules()[$moduleType] ?? $moduleType;
    }
}
