<?php

namespace App\Services\Api;

use App\Models\CustomField;
use Illuminate\Database\Eloquent\Builder;

class RansackFilter
{
    /**
     * Predicates ordered longest-suffix-first to prevent partial matches.
     *
     * For example, `not_cont` must be matched before `cont`, and `not_null`
     * before `null`, to avoid `status_not_cont` being parsed as field
     * `status_not` with predicate `cont`.
     *
     * @var list<string>
     */
    private const PREDICATES = [
        'not_cont',
        'not_null',
        'not_eq',
        'not_in',
        'matches',
        'present',
        'blank',
        'false',
        'start',
        'lteq',
        'gteq',
        'cont',
        'null',
        'true',
        'end',
        'lt',
        'gt',
        'eq',
        'in',
    ];

    /**
     * Apply Ransack-compatible filters to a query builder.
     *
     * Filter keys are parsed as `{field}_{predicate}`. Filters on fields
     * not present in `$allowedFields` are silently ignored for security.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters  Keyed by `field_predicate`
     * @param  list<string>  $allowedFields  Whitelist of filterable fields
     * @param  list<string>  $allowedRelationFilters  Whitelist of filterable relations (e.g. `['addresses', 'phones']`)
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function apply(
        Builder $query,
        array $filters,
        array $allowedFields,
        array $allowedRelationFilters = [],
        ?string $customFieldModule = null,
    ): Builder {
        foreach ($filters as $key => $value) {
            // Custom field filters: cf.field_name_predicate or numeric field ID
            if ($customFieldModule !== null && str_starts_with($key, 'cf.')) {
                $cfKey = substr($key, 3);
                $parsed = $this->parseKey($cfKey);

                if ($parsed !== null) {
                    $this->applyCustomFieldFilter($query, $parsed[0], $parsed[1], $value, $customFieldModule);
                }

                continue;
            }

            $parsed = $this->parseKey($key);

            if ($parsed === null) {
                continue;
            }

            [$field, $predicate] = $parsed;

            // Relationship filtering: field contains dot (e.g. addresses.city_eq)
            if (str_contains($field, '.')) {
                [$relation, $column] = explode('.', $field, 2);

                if (in_array($relation, $allowedRelationFilters, true)) {
                    $query->whereHas($relation, fn (Builder $q) => $this->applyPredicate($q, $column, $predicate, $value));
                }

                continue;
            }

            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            $this->applyPredicate($query, $field, $predicate, $value);
        }

        return $query;
    }

    /**
     * Apply Ransack-compatible sorting to a query builder.
     *
     * Accepts `field` for ascending or `-field` for descending order.
     * Sort on fields not in `$allowedFields` is silently ignored.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $allowedFields  Whitelist of sortable fields
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function applySort(Builder $query, string $sort, array $allowedFields): Builder
    {
        if ($sort === '') {
            return $query;
        }

        if (str_starts_with($sort, '-')) {
            $field = substr($sort, 1);
            $direction = 'desc';
        } else {
            $field = $sort;
            $direction = 'asc';
        }

        if (! in_array($field, $allowedFields, true)) {
            return $query;
        }

        return $query->orderBy($field, $direction);
    }

    /**
     * Parse a filter key into [field, predicate].
     *
     * Matches the longest predicate suffix first to avoid partial matches
     * (e.g. `status_not_cont` correctly yields `['status', 'not_cont']`
     * rather than `['status_not', 'cont']`).
     *
     * @return array{0: string, 1: string}|null
     */
    private function parseKey(string $key): ?array
    {
        foreach (self::PREDICATES as $predicate) {
            $suffix = '_'.$predicate;

            if (str_ends_with($key, $suffix)) {
                $field = substr($key, 0, -strlen($suffix));

                if ($field !== '') {
                    return [$field, $predicate];
                }
            }
        }

        return null;
    }

    /**
     * Apply a single predicate condition to the query builder.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyPredicate(Builder $query, string $field, string $predicate, mixed $value): void
    {
        match ($predicate) {
            'eq' => $query->where($field, '=', $value),
            'not_eq' => $query->where($field, '!=', $value),
            'lt' => $query->where($field, '<', $value),
            'lteq' => $query->where($field, '<=', $value),
            'gt' => $query->where($field, '>', $value),
            'gteq' => $query->where($field, '>=', $value),
            'cont' => $query->where($field, 'ilike', '%'.self::escapeLike($value).'%'),
            'not_cont' => $query->where($field, 'not ilike', '%'.self::escapeLike($value).'%'),
            'start' => $query->where($field, 'ilike', self::escapeLike($value).'%'),
            'end' => $query->where($field, 'ilike', '%'.self::escapeLike($value)),
            'null' => $query->whereNull($field),
            'not_null' => $query->whereNotNull($field),
            'present' => $query->whereNotNull($field)->where($field, '!=', ''),
            'blank' => $query->where(fn (Builder $q) => $q->whereNull($field)->orWhere($field, '=', '')),
            'matches' => $query->whereRaw("\"{$field}\" ~* ?", [(string) $value]),
            'in' => $query->whereIn($field, is_array($value) ? $value : explode(',', (string) $value)),
            'not_in' => $query->whereNotIn($field, is_array($value) ? $value : explode(',', (string) $value)),
            'true' => $query->where($field, '=', true),
            'false' => $query->where($field, '=', false),
            default => null,
        };
    }

    /**
     * Apply a custom field filter using EAV storage.
     *
     * Supports filtering by field name or numeric field ID (CRMS compatibility).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyCustomFieldFilter(
        Builder $query,
        string $fieldName,
        string $predicate,
        mixed $value,
        string $moduleType,
    ): void {
        $customField = ctype_digit($fieldName)
            ? CustomField::query()->where('id', (int) $fieldName)->where('module_type', $moduleType)->first()
            : CustomField::query()->where('name', $fieldName)->where('module_type', $moduleType)->first();

        if ($customField === null) {
            return;
        }

        /** @var \App\Enums\CustomFieldType $fieldType */
        $fieldType = $customField->field_type;
        $valueColumn = $fieldType->valueColumn();

        $query->whereHas('customFieldValues', function (Builder $q) use ($customField, $valueColumn, $predicate, $value): void {
            $q->where('custom_field_id', $customField->id);
            $this->applyPredicate($q, $valueColumn, $predicate, $value);
        });
    }

    /**
     * Escape LIKE/ILIKE wildcard characters in a user-provided value.
     */
    private static function escapeLike(mixed $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            (string) $value
        );
    }
}
