<?php

namespace App\Services;

use App\Views\ColumnRegistry;
use App\Views\MemberColumnRegistry;
use Illuminate\Validation\ValidationException;

/**
 * Maps entity type strings to their corresponding ColumnRegistry classes.
 */
class ColumnRegistryResolver
{
    /** @var array<string, class-string<ColumnRegistry>> */
    private array $map = [
        'members' => MemberColumnRegistry::class,
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
