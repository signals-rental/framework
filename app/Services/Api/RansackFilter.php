<?php

namespace App\Services\Api;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Support\BackedEnumHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RansackFilter
{
    /**
     * Sentinel returned for an unrecognised value on an int-backed enum column.
     *
     * A non-numeric string can never equal a stored integer, so it yields an
     * empty result set rather than over-matching. Without this, an unrecognised
     * string would be DB-cast to `0` on int columns and erroneously match rows
     * whose enum backing value is `0`.
     */
    private const INT_ENUM_NO_MATCH = '__signals_no_match__';

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
     * The supported Ransack predicate suffixes (without the leading underscore).
     *
     * @return list<string>
     */
    public static function predicates(): array
    {
        return self::PREDICATES;
    }

    /**
     * Apply Ransack-compatible filters to a query builder.
     *
     * Filter keys are parsed as `{field}_{predicate}`. Filters on fields
     * not present in `$allowedFields` are silently ignored for security.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters  Keyed by `field_predicate`
     * @param  list<string>  $allowedFields  Whitelist of filterable fields
     * @param  array<string, list<string>>  $allowedRelationFilters  Map of relation => allowed columns (e.g. `['productGroup' => ['name']]`)
     * @return Builder<TModel>
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

            // Relationship filtering: field contains a dot (e.g. productGroup.name_eq).
            // Both the relation AND the column must be whitelisted via the
            // relation=>[columns] map, preventing filtering on arbitrary related columns.
            if (str_contains($field, '.')) {
                [$relation, $column] = explode('.', $field, 2);

                $allowedColumns = $allowedRelationFilters[$relation] ?? null;
                if (is_array($allowedColumns) && in_array($column, $allowedColumns, true)) {
                    $query->whereHas($relation, fn (Builder $q) => $this->applyPredicate($q, $column, $predicate, $value));
                }

                continue;
            }

            if (! in_array($field, $allowedFields, true)) {
                continue;
            }

            // JSONB array columns (cast to array/json/collection on the model)
            // cannot be filtered with the scalar string operators below — an
            // ilike against a jsonb array errors/no-ops on PostgreSQL. Route the
            // membership predicates through whereJsonContains, which is driver-safe
            // (works on both SQLite and PostgreSQL).
            if ($this->isJsonColumn($query, $field)) {
                $this->applyJsonPredicate($query, $field, $predicate, $value);

                continue;
            }

            $value = $this->coerceEnumValue($query, $field, $predicate, $value);

            $this->applyPredicate($query, $field, $predicate, $value);
        }

        return $query;
    }

    /**
     * Whether the given field is a JSON/JSONB-backed column on the query's model
     * — i.e. cast to one of the JSON-family Eloquent casts. Such columns store an
     * array, so they need membership (whereJsonContains) filtering rather than
     * the scalar string operators.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function isJsonColumn(Builder $query, string $field): bool
    {
        $cast = $query->getModel()->getCasts()[$field] ?? null;

        return is_string($cast) && in_array($cast, ['array', 'json', 'object', 'collection'], true);
    }

    /**
     * Apply a predicate to a JSON/JSONB array column.
     *
     * `cont`/`eq`/`in` test array MEMBERSHIP via whereJsonContains (an `in` value
     * matches when ANY listed value is present); `null`/`blank`/`present`/
     * `not_null` test column presence/emptiness. Other predicates are unsupported
     * on a JSON array and are ignored (no-op) rather than producing an invalid
     * scalar comparison.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyJsonPredicate(Builder $query, string $field, string $predicate, mixed $value): void
    {
        match ($predicate) {
            'cont', 'eq' => $query->whereJsonContains($field, $value),
            'not_cont', 'not_eq' => $query->whereJsonDoesntContain($field, $value),
            'in' => $query->where(function (Builder $q) use ($field, $value): void {
                foreach (is_array($value) ? $value : explode(',', (string) $value) as $item) {
                    $q->orWhereJsonContains($field, $item);
                }
            }),
            'not_in' => $query->where(function (Builder $q) use ($field, $value): void {
                foreach (is_array($value) ? $value : explode(',', (string) $value) as $item) {
                    $q->whereJsonDoesntContain($field, $item);
                }
            }),
            'null', 'blank' => $query->where(fn (Builder $q) => $q->whereNull($field)->orWhereJsonLength($field, 0)),
            'not_null', 'present' => $query->whereNotNull($field)->whereJsonLength($field, '>', 0),
            default => null,
        };
    }

    /**
     * Coerce an incoming filter value to its canonical backed-enum value when
     * the target column is cast to a BackedEnum on the query's model.
     *
     * This makes equality-family filters on enum columns case-insensitive: a
     * caller may send the enum's backing value (`rental`), its case name
     * (`Rental`), or any casing thereof, and it resolves to the stored value.
     * Non-enum columns and non-equality predicates are returned untouched.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function coerceEnumValue(Builder $query, string $field, string $predicate, mixed $value): mixed
    {
        if (! in_array($predicate, ['eq', 'not_eq', 'in', 'not_in'], true)) {
            return $value;
        }

        $casts = $query->getModel()->getCasts();
        $cast = $casts[$field] ?? null;

        if (! is_string($cast) || ! enum_exists($cast) || ! is_subclass_of($cast, \BackedEnum::class)) {
            return $value;
        }

        if ($predicate === 'in' || $predicate === 'not_in') {
            $values = is_array($value) ? $value : explode(',', (string) $value);

            return array_map(fn (mixed $item): mixed => $this->resolveEnumValue($cast, $item), $values);
        }

        return $this->resolveEnumValue($cast, $value);
    }

    /**
     * Resolve a single value to the canonical backing value of a backed enum,
     * matching case-insensitively against both backing values and case names.
     *
     * On no match: string-backed enums return the original value (so an
     * unrecognised string still produces a zero-result query rather than an
     * error). Int-backed enums instead return a non-numeric sentinel — without
     * it, an unrecognised string would be DB-cast to `0` on the int column and
     * over-match any rows whose enum backing value happens to be `0`.
     *
     * @param  class-string<\BackedEnum>  $enumClass
     */
    private function resolveEnumValue(string $enumClass, mixed $value): mixed
    {
        $isIntBacked = (string) (new \ReflectionEnum($enumClass))->getBackingType() === 'int';

        return BackedEnumHelper::coerce(
            $enumClass,
            $value,
            $isIntBacked
                ? fn (mixed $original): mixed => is_string($original) ? self::INT_ENUM_NO_MATCH : $original
                : null,
        );
    }

    /**
     * Apply Ransack-compatible sorting to a query builder.
     *
     * Accepts `field` for ascending or `-field` for descending order.
     * Sort on fields not in `$allowedFields` is silently ignored.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $allowedFields  Whitelist of sortable fields
     * @return Builder<TModel>
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
            'matches' => $query->whereRaw($query->getGrammar()->wrap($field).' ~* ?', [(string) $value]),
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
     * Supports filtering by field name or numeric field ID (RMS compatibility).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyCustomFieldFilter(
        Builder $query,
        string $fieldName,
        string $predicate,
        mixed $value,
        string $moduleType,
    ): void {
        $customField = ctype_digit($fieldName)
            ? CustomField::query()->where('id', (int) $fieldName)->where('module_type', $moduleType)->active()->first()
            : CustomField::query()->where('name', $fieldName)->where('module_type', $moduleType)->active()->first();

        if ($customField === null || ! $customField->is_searchable) {
            return;
        }

        /** @var CustomFieldType $fieldType */
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
    public static function escapeLike(mixed $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            (string) $value
        );
    }
}
