<?php

namespace App\Models\Traits;

trait HasCostVisibility
{
    /**
     * Check if the authenticated user can view cost-related fields.
     */
    public function canViewCosts(): bool
    {
        return auth()->user()?->can('costs.view') ?? false;
    }

    /**
     * Get the list of cost-related column names for this model.
     *
     * Override in models that have cost columns.
     *
     * @return list<string>
     */
    public function costColumns(): array
    {
        return property_exists($this, 'costColumns') ? $this->costColumns : [];
    }

    /**
     * Strip cost fields from a data array if the user cannot view costs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withoutCostColumns(array $data): array
    {
        if ($this->canViewCosts()) {
            return $data;
        }

        return array_diff_key($data, array_flip($this->costColumns()));
    }
}
