<?php

namespace App\Services;

/**
 * Evaluates custom field visibility rules against entity data.
 *
 * Visibility rules determine whether a custom field should be shown
 * based on the values of other fields on the same entity. Rules are
 * stored as JSON arrays on the custom_fields table.
 *
 * Rule format:
 *   [
 *     {"field": "membership_type", "operator": "eq", "value": "Organisation"},
 *     {"field": "is_active", "operator": "eq", "value": true}
 *   ]
 *
 * Multiple rules use AND logic — all must match for the field to be visible.
 */
class VisibilityRuleEvaluator
{
    /**
     * Evaluate whether a field should be visible given the entity data.
     *
     * Returns true if the field should be shown (no rules, or all rules pass).
     *
     * @param  array<int, array<string, mixed>>|null  $rules
     * @param  array<string, mixed>  $entityData
     */
    public function evaluate(?array $rules, array $entityData): bool
    {
        if ($rules === null || $rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! $this->evaluateRule($rule, $entityData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Filter a list of custom field definitions to only those visible for the given data.
     *
     * @param  array<int, array{visibility_rules?: array<int, array<string, mixed>>|null}>  $fields
     * @param  array<string, mixed>  $entityData
     * @return array<int, array<string, mixed>>
     */
    public function filterVisible(array $fields, array $entityData): array
    {
        return array_values(array_filter($fields, function (array $field) use ($entityData): bool {
            return $this->evaluate($field['visibility_rules'] ?? null, $entityData);
        }));
    }

    /**
     * Evaluate a single visibility rule against entity data.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $entityData
     */
    private function evaluateRule(array $rule, array $entityData): bool
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? 'eq';
        $expected = $rule['value'] ?? null;

        if ($field === null) {
            \Illuminate\Support\Facades\Log::warning('Visibility rule missing field key', ['rule' => $rule]);

            return true;
        }

        $actual = $entityData[$field] ?? null;

        return match ($operator) {
            'eq' => $this->looseEquals($actual, $expected),
            'not_eq', 'neq' => ! $this->looseEquals($actual, $expected),
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, false),
            'present' => $actual !== null && $actual !== '',
            'blank' => $actual === null || $actual === '',
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            default => tap(true, fn () => \Illuminate\Support\Facades\Log::warning("Unknown visibility rule operator: {$operator}", $rule)),
        };
    }

    /**
     * Loose equality that handles type coercion for boolean/numeric comparisons.
     */
    private function looseEquals(mixed $actual, mixed $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }

        // Handle boolean comparisons (JSON may store as string)
        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        if (is_bool($actual)) {
            return $actual === (bool) $expected;
        }

        // Handle numeric comparisons
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        // String comparison (case-insensitive)
        if (is_string($actual) && is_string($expected)) {
            return strtolower($actual) === strtolower($expected);
        }

        return false;
    }
}
