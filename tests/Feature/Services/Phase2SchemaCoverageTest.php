<?php

use App\Contracts\HasSchema;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Services\SchemaRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Phase-2 DoD item #116 — field-registry coverage guard.
 *
 * Verifies that every Phase-2 entity exposes its full editable surface through
 * the field registry (and therefore through `GET /api/v1/schema/{model}`), not
 * merely that the model implements HasSchema. The guard is driven off each
 * model's `$fillable` array, so any column added without a matching
 * defineSchema() entry will trip this test.
 *
 * Presentation-only columns (icon URLs, thumbnails) are intentionally excluded
 * from the queryable schema and are listed per-model below.
 *
 * @var array<class-string, array<int, string>> $phase2Models
 */
$phase2Models = [
    // container_template is a structural JSONB blob (slot composition config),
    // not a queryable/filterable scalar — intentionally kept out of the schema,
    // mirroring how other non-scalar structural columns are excluded.
    Product::class => ['icon_url', 'icon_thumb_url', 'container_template'],
    ProductGroup::class => ['icon_url', 'icon_thumb_url'],
    StockLevel::class => [],
    StockTransaction::class => [],
    RateDefinition::class => [],
    ProductRate::class => [],
];

describe('Phase 2 field-registry coverage', function () use ($phase2Models) {
    it('declares every Phase-2 model as HasSchema', function (string $modelClass) {
        expect(is_subclass_of($modelClass, HasSchema::class))
            ->toBeTrue("{$modelClass} must implement HasSchema so it appears in /api/v1/schema/{model}");
    })->with(array_keys($phase2Models));

    it('covers the full editable surface in the resolved schema', function (string $modelClass, array $excluded) {
        /** @var Model $instance */
        $instance = new $modelClass;

        $fillable = $instance->getFillable();
        expect($fillable)->not->toBeEmpty("{$modelClass} should declare a fillable surface");

        $schema = (new SchemaRegistry)->resolve($modelClass);
        $schemaKeys = array_keys($schema);

        $expected = array_values(array_diff($fillable, $excluded));
        $missing = array_values(array_diff($expected, $schemaKeys));

        expect($missing)->toBe(
            [],
            sprintf(
                '%s exposes %s via $fillable but does not register %s in defineSchema(); '
                .'they would be absent from /api/v1/schema/%s.',
                class_basename($modelClass),
                implode(', ', $expected),
                implode(', ', $missing),
                Str::plural(Str::snake(class_basename($modelClass))),
            ),
        );
    })->with(array_map(
        fn (string $modelClass, array $excluded): array => [$modelClass, $excluded],
        array_keys($phase2Models),
        array_values($phase2Models),
    ));

    it('returns non-empty schema fields for every Phase-2 model', function (string $modelClass) {
        $schema = (new SchemaRegistry)->resolve($modelClass);

        expect($schema)->not->toBeEmpty("{$modelClass} resolved an empty schema — its /schema endpoint would return no fields");
    })->with(array_keys($phase2Models));
});
