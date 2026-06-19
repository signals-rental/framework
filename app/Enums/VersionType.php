<?php

namespace App\Enums;

/**
 * The kind of quote version (opportunity-lifecycle.md §8.6).
 *
 * A REVISION is a sequential iteration of the quote — creating one supersedes
 * its parent version. ALTERNATIVES are parallel options the customer chooses
 * between; they coexist without superseding one another.
 *
 * Values are RMS-aligned integers persisted on `opportunity_versions.version_type`.
 */
enum VersionType: int
{
    case Revision = 0;

    case Alternative = 1;

    public function label(): string
    {
        return match ($this) {
            self::Revision => 'Revision',
            self::Alternative => 'Alternative',
        };
    }
}
