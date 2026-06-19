<?php

namespace App\Services;

use App\Views\ActivityColumnRegistry;
use App\Views\ColumnRegistry;
use App\Views\ContainerColumnRegistry;
use App\Views\MemberColumnRegistry;
use App\Views\OpportunityColumnRegistry;
use App\Views\OpportunityCostColumnRegistry;
use App\Views\OpportunityItemColumnRegistry;
use App\Views\OpportunityVersionColumnRegistry;
use App\Views\ProductColumnRegistry;
use App\Views\ProductGroupColumnRegistry;
use App\Views\ProductRateColumnRegistry;
use App\Views\RateDefinitionColumnRegistry;
use App\Views\SerialisedComponentColumnRegistry;
use App\Views\ShortageResolutionColumnRegistry;
use App\Views\StockLevelColumnRegistry;
use Illuminate\Validation\ValidationException;

/**
 * Maps entity type strings to their corresponding ColumnRegistry classes.
 *
 * This is the single source of truth for the entity-type → registry mapping.
 * Consumers (e.g. DataTable, custom-view validation) resolve through here so a
 * new module only needs to register its registry in one place.
 */
class ColumnRegistryResolver
{
    /** @var array<string, class-string<ColumnRegistry>> */
    private array $map = [
        'members' => MemberColumnRegistry::class,
        'products' => ProductColumnRegistry::class,
        'product_groups' => ProductGroupColumnRegistry::class,
        'stock_levels' => StockLevelColumnRegistry::class,
        'activities' => ActivityColumnRegistry::class,
        'rate_definitions' => RateDefinitionColumnRegistry::class,
        'product_rates' => ProductRateColumnRegistry::class,
        'opportunities' => OpportunityColumnRegistry::class,
        'opportunity_items' => OpportunityItemColumnRegistry::class,
        'opportunity_costs' => OpportunityCostColumnRegistry::class,
        'opportunity_versions' => OpportunityVersionColumnRegistry::class,
        'containers' => ContainerColumnRegistry::class,
        'serialised_components' => SerialisedComponentColumnRegistry::class,
        'shortage_resolutions' => ShortageResolutionColumnRegistry::class,
    ];

    /** @var array<string, ColumnRegistry> */
    private array $instances = [];

    /**
     * Resolve the ColumnRegistry for a given entity type.
     */
    public function resolve(string $entityType): ?ColumnRegistry
    {
        if (isset($this->instances[$entityType])) {
            return $this->instances[$entityType];
        }

        $class = $this->map[$entityType] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->instances[$entityType] = new $class;
    }

    /**
     * Register a ColumnRegistry class for an entity type.
     *
     * @param  class-string<ColumnRegistry>  $registryClass
     */
    public function register(string $entityType, string $registryClass): void
    {
        $this->map[$entityType] = $registryClass;

        unset($this->instances[$entityType]);
    }

    /**
     * Get all registered entity types.
     *
     * @return list<string>
     */
    public function entityTypes(): array
    {
        return array_keys($this->map);
    }

    /**
     * Validate column and sort_column keys against the entity's ColumnRegistry.
     *
     * Silently passes if no registry is registered for the entity type.
     *
     * @param  list<string>  $columns
     *
     * @throws ValidationException
     */
    public function validateColumns(string $entityType, array $columns, ?string $sortColumn = null): void
    {
        $registry = $this->resolve($entityType);

        if ($registry === null) {
            return;
        }

        $invalid = $registry->validateColumns($columns);

        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'columns' => ['Invalid column keys: '.implode(', ', $invalid)],
            ]);
        }

        if ($sortColumn !== null && $registry->get($sortColumn) === null) {
            throw ValidationException::withMessages([
                'sort_column' => ["Invalid sort column: {$sortColumn}"],
            ]);
        }
    }
}
