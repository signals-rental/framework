<?php

namespace App\Models\Traits;

use App\Models\User;

trait HasCostVisibility
{
    /**
     * Check if a user can view cost-related fields.
     *
     * Accepts an explicit user for queue/CLI contexts. Falls back to the
     * authenticated user when none is provided.
     */
    public function canViewCosts(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user?->can('costs.view') ?? false;
    }

    /**
     * Get the list of cost-related column names for this model.
     *
     * Models using this trait must declare a `protected array $costColumns` property
     * listing their cost-sensitive column names.
     *
     * @return list<string>
     */
    public function costColumns(): array
    {
        if (! property_exists($this, 'costColumns')) {
            throw new \LogicException(
                static::class.' uses HasCostVisibility but does not declare a $costColumns property.'
            );
        }

        return $this->costColumns;
    }

    /**
     * Strip cost fields from a data array if the user cannot view costs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutCostColumns(array $data, ?User $user = null): array
    {
        if ($this->canViewCosts($user)) {
            return $data;
        }

        return array_diff_key($data, array_flip($this->costColumns()));
    }
}
