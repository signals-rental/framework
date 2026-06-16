<?php

namespace App\Support;

use App\Enums\RateTransactionType;
use App\Services\Api\RansackFilter;
use BackedEnum;

/**
 * Shared case-insensitive coercion for backed enums.
 *
 * Centralises the logic that matches an arbitrary incoming value (a backing
 * value, a case name, or any casing thereof) to a backed enum's canonical
 * backing value. Used by both the Ransack read path ({@see RansackFilter})
 * and enum write coercion (e.g. {@see RateTransactionType::coerce()})
 * so the matching rules never diverge between reads and writes.
 */
class BackedEnumHelper
{
    /**
     * Resolve a value to the canonical backing value of a backed enum, matching
     * case-insensitively against both backing values and case names.
     *
     * Non-scalar input (anything that is neither a string nor an int) is returned
     * untouched. When no case matches, the supplied `$default` is returned — by
     * default the original value, so callers that want a never-matching sentinel
     * (e.g. to avoid an unrecognised string being DB-cast to `0` on int-backed
     * enums) can pass their own fallback.
     *
     * @param  class-string<BackedEnum>  $enumClass
     * @param  callable(mixed): mixed|null  $default  Resolver for the no-match case; receives the original value. Defaults to returning it untouched.
     */
    public static function coerce(string $enumClass, mixed $value, ?callable $default = null): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        $needle = mb_strtolower((string) $value);

        foreach ($enumClass::cases() as $case) {
            if (mb_strtolower((string) $case->value) === $needle || mb_strtolower($case->name) === $needle) {
                return $case->value;
            }
        }

        return $default !== null ? $default($value) : $value;
    }
}
