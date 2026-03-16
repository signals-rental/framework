<?php

namespace App\Services;

use App\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves and caches active custom field definitions per module type.
 *
 * Shared by CustomFieldSerializer and CustomFieldValidator to avoid
 * redundant queries when both are called in the same request.
 */
class CustomFieldDefinitionResolver
{
    /** @var array<string, Collection<int, CustomField>> */
    private array $cache = [];

    /**
     * Get all active custom field definitions for a module type, ordered by sort_order.
     *
     * @return Collection<int, CustomField>
     */
    public function resolve(string $moduleType): Collection
    {
        return $this->cache[$moduleType] ??= CustomField::query()
            ->forModule($moduleType)
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Clear the definitions cache. Call after modifying custom field definitions
     * (e.g. creating, updating, or deactivating fields).
     */
    public function clearCache(?string $moduleType = null): void
    {
        if ($moduleType !== null) {
            unset($this->cache[$moduleType]);
        } else {
            $this->cache = [];
        }
    }
}
